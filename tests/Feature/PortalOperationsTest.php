<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ActivityLog;
use App\Models\User;
use App\Notifications\PortalNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class PortalOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_account_email_and_password_changes_require_the_current_password(): void
    {
        $student = $this->createStudent();
        $this->actingAs($student)
            ->from(route('account.settings'))
            ->put(route('account.settings.update'), [
                'email' => 'updated@example.test',
            ])
            ->assertSessionHasErrors('current_password');

        $this->put(route('account.settings.update'), [
            'email' => 'updated@example.test',
            'current_password' => 'password',
            'password' => 'a-new-secure-password',
            'password_confirmation' => 'a-new-secure-password',
        ])->assertRedirect();

        $student->refresh();
        $this->assertSame('updated@example.test', $student->email);
        $this->assertTrue(Hash::check('a-new-secure-password', $student->password));
    }

    public function test_account_settings_save_changes_the_email_with_current_password(): void
    {
        $student = $this->createStudent();

        $this->actingAs($student)
            ->put(route('account.settings.update'), [
                'email' => 'updated@example.test',
                'current_password' => 'password',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Account settings updated.');

        $this->assertSame('updated@example.test', $student->fresh()->email);
    }

    public function test_users_can_only_read_and_mark_their_own_notifications(): void
    {
        $student = $this->createStudent();
        $otherStudent = $this->createStudent('other');
        $notification = new PortalNotification('Request update', 'Ready for pickup.', route('dashboard'), 'request_status');
        $student->notify($notification);
        $otherStudent->notify($notification);
        $otherNotification = $otherStudent->notifications()->firstOrFail();

        $this->actingAs($student)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonCount(1, 'notifications');

        $this->patchJson(route('notifications.read', $otherNotification->id))->assertNotFound();
        $this->assertNull($otherNotification->fresh()->read_at);

        $studentNotification = $student->notifications()->firstOrFail();
        $this->patchJson(route('notifications.read', $studentNotification->id))
            ->assertOk();
        $this->assertNotNull($studentNotification->fresh()->read_at);
    }

    public function test_password_reset_link_is_sent_through_laravels_password_broker(): void
    {
        Notification::fake();
        $student = $this->createStudent();

        $this->post(route('password.email'), ['email' => $student->email])
            ->assertRedirect()
            ->assertSessionHas('status');

        Notification::assertSentTo($student, ResetPassword::class);
    }

    public function test_account_settings_can_email_the_authenticated_users_password_reset_link(): void
    {
        Notification::fake();
        $student = $this->createStudent();
        $otherStudent = $this->createStudent('other');

        $this->actingAs($student);
        $this->get(route('account.settings'))
            ->assertOk()
            ->assertSee('Save changes')
            ->assertSee('Reset password')
            ->assertSee('To change your email, enter your current password below and select Save changes.');

        $this->post(route('account.password-reset.email'), ['email' => $otherStudent->email])
            ->assertRedirect()
            ->assertSessionHas('status', 'A password reset link was sent to '.$student->email.'.');

        Notification::assertSentTo($student, ResetPassword::class);
        Notification::assertNotSentTo($otherStudent, ResetPassword::class);
    }

    public function test_smtp_transport_failure_is_logged_and_shown_without_exposing_a_stack_trace(): void
    {
        $student = $this->createStudent();
        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => $student->email])
            ->andThrow(new TransportException('SMTP authentication failed.'));
        Log::shouldReceive('error')
            ->once()
            ->with('Password reset email delivery failed.', \Mockery::on(fn (array $context) => $context['user_id'] === $student->id
                && $context['exception_class'] === TransportException::class));

        $this->actingAs($student)
            ->from(route('account.settings'))
            ->post(route('account.password-reset.email'))
            ->assertRedirect(route('account.settings'))
            ->assertSessionHas('error', 'The reset email could not be sent. Check the mail configuration or contact the administrator.');
    }

    public function test_activity_log_excludes_passwords_from_user_creation_data(): void
    {
        $student = $this->createStudent();
        $log = ActivityLog::where('subject_type', User::class)
            ->where('subject_id', $student->id)
            ->where('action', 'created')
            ->firstOrFail();

        $this->assertNull($log->changes);
    }

    private function createStudent(string $prefix = 'student'): User
    {
        return User::factory()->create([
            'name' => ucfirst($prefix).' Test',
            'firstname' => ucfirst($prefix),
            'surname' => 'Test',
            'school_id' => $prefix.'-001',
            'email' => $prefix.'@example.test',
            'password' => 'password',
            'role' => Role::Student,
            'status' => UserStatus::Activated,
        ]);
    }
}

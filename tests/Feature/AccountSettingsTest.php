<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Account settings (port of the legacy accountsettings page): every role can read its own
 * details, edit its own profile and change its own password; nothing else about the account
 * (ID, email, role, status, department) can be changed from here, and nobody can reach
 * another user's account through these routes.
 */
class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PASSWORD = 'current-password-123';

    private const NEW_PASSWORD = 'a-brand-new-passphrase';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['smartdoc.automation.tick_seconds' => 0]);
    }

    // ---- access ---------------------------------------------------------------

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('account.edit'))->assertRedirect(route('login'));
        $this->put(route('account.profile'), ['firstname' => 'x'])->assertRedirect(route('login'));
        $this->put(route('account.password'), ['password' => 'x'])->assertRedirect(route('login'));
    }

    public function test_every_role_sees_its_own_details_and_the_sidebar_link(): void
    {
        foreach ([Role::Student, Role::Staff, Role::Admin] as $role) {
            $user = $this->user($role->value, $role);
            $other = $this->user('other'.$role->value, Role::Student);

            $this->actingAs($user)->get(route('account.edit'))
                ->assertOk()
                ->assertSee('Account Settings')
                ->assertSee($user->school_id)
                ->assertSee($user->email)
                ->assertSee($role->label())
                ->assertSee('Account settings') // sidebar link
                ->assertSee(e(route('account.edit')), false)
                ->assertDontSee($other->email)
                ->assertDontSee($other->school_id);
        }
    }

    public function test_the_page_only_shows_academic_fields_to_students(): void
    {
        $student = $this->user('stu', Role::Student, ['course' => 'BSIT', 'year' => 3, 'section' => 'A']);
        $staff = $this->user('stf', Role::Staff);

        $this->actingAs($student)->get(route('account.edit'))
            ->assertOk()->assertSee('name="course"', false)->assertSee('name="year"', false)->assertSee('name="section"', false)
            ->assertSee('BSIT');

        $this->actingAs($staff)->get(route('account.edit'))
            ->assertOk()->assertDontSee('name="course"', false)->assertDontSee('name="year"', false);
    }

    public function test_identity_fields_are_read_only_and_not_submittable(): void
    {
        $student = $this->user('stu', Role::Student);

        $html = $this->actingAs($student)->get(route('account.edit'))->assertOk()->getContent();

        foreach (['name="school_id"', 'name="email"', 'name="role"', 'name="status"', 'name="department_id"'] as $field) {
            $this->assertStringNotContainsString($field, $html);
        }
    }

    public function test_the_page_is_read_only_over_get_and_unsupported_methods_are_rejected(): void
    {
        $student = $this->user('stu', Role::Student);

        $before = $student->fresh()->toArray();
        $this->actingAs($student)->get(route('account.edit'))->assertOk();
        $this->assertSame($before, $student->fresh()->toArray());

        $this->actingAs($student)->post('/account-settings')->assertStatus(405);
        $this->actingAs($student)->delete('/account-settings')->assertStatus(405);
        $this->actingAs($student)->get('/account-settings/profile')->assertStatus(405);
        $this->actingAs($student)->get('/account-settings/password')->assertStatus(405);
    }

    // ---- profile --------------------------------------------------------------

    public function test_a_student_can_update_their_profile_and_the_name_column_follows(): void
    {
        $student = $this->user('stu', Role::Student);
        $bystander = $this->user('bystander', Role::Student, ['course' => 'BSCS']);

        $this->actingAs($student)->from(route('account.edit'))
            ->put(route('account.profile'), [
                'firstname' => 'Maria',
                'surname' => 'Santos',
                'course' => 'BSIT',
                'year' => '3',
                'section' => 'B',
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('status', 'Your profile has been updated.')
            ->assertSessionHasNoErrors();

        $fresh = $student->fresh();
        $this->assertSame('Maria', $fresh->firstname);
        $this->assertSame('Santos', $fresh->surname);
        $this->assertSame('Maria Santos', $fresh->name);
        $this->assertSame('BSIT', $fresh->course);
        $this->assertSame(3, (int) $fresh->year);
        $this->assertSame('B', $fresh->section);

        // Nobody else was touched.
        $this->assertSame('BSCS', $bystander->fresh()->course);
        $this->assertSame('bystander', $bystander->fresh()->firstname);
    }

    public function test_protected_fields_cannot_be_changed_through_the_profile_form(): void
    {
        $student = $this->user('stu', Role::Student);
        $original = $student->only(['school_id', 'email', 'role', 'status', 'department_id', 'password']);

        $this->actingAs($student)->from(route('account.edit'))
            ->put(route('account.profile'), [
                'firstname' => 'Maria',
                'surname' => 'Santos',
                'school_id' => 'HACKED-1',
                'email' => 'hacked@example.test',
                'role' => 'admin',
                'status' => 'deactivated',
                'department_id' => 1,
                'password' => 'sneaky-password-12345',
                'id' => 9999,
            ])
            ->assertRedirect(route('account.edit'));

        $fresh = $student->fresh();

        $this->assertSame($student->id, $fresh->id);
        $this->assertSame($original['school_id'], $fresh->school_id);
        $this->assertSame($original['email'], $fresh->email);
        $this->assertSame(Role::Student, $fresh->role);
        $this->assertSame(UserStatus::Activated, $fresh->status);
        $this->assertSame($original['department_id'], $fresh->department_id);
        $this->assertSame($original['password'], $fresh->password);
        $this->assertSame('Maria', $fresh->firstname);
    }

    public function test_staff_and_admin_cannot_store_academic_details(): void
    {
        foreach ([Role::Staff, Role::Admin] as $role) {
            $user = $this->user('x'.$role->value, $role);

            $this->actingAs($user)->from(route('account.edit'))
                ->put(route('account.profile'), [
                    'firstname' => 'Pat',
                    'surname' => 'Reyes',
                    'course' => 'BSIT',
                    'year' => '2',
                    'section' => 'C',
                ])
                ->assertRedirect(route('account.edit'));

            $fresh = $user->fresh();
            $this->assertSame('Pat', $fresh->firstname);
            $this->assertNull($fresh->course);
            $this->assertNull($fresh->year);
            $this->assertNull($fresh->section);
        }
    }

    public function test_profile_validation_rejects_bad_input_and_changes_nothing(): void
    {
        $student = $this->user('stu', Role::Student, ['course' => 'BSIT']);

        $invalid = [
            'missing first name' => [['firstname' => '', 'surname' => 'Santos'], 'firstname'],
            'missing surname' => [['firstname' => 'Maria', 'surname' => ''], 'surname'],
            'first name too long' => [['firstname' => str_repeat('a', 101), 'surname' => 'Santos'], 'firstname'],
            'surname too long' => [['firstname' => 'Maria', 'surname' => str_repeat('a', 101)], 'surname'],
            'course too long' => [['firstname' => 'Maria', 'surname' => 'Santos', 'course' => str_repeat('a', 101)], 'course'],
            'section too long' => [['firstname' => 'Maria', 'surname' => 'Santos', 'section' => str_repeat('a', 51)], 'section'],
            'year not a number' => [['firstname' => 'Maria', 'surname' => 'Santos', 'year' => 'abc'], 'year'],
            'year zero' => [['firstname' => 'Maria', 'surname' => 'Santos', 'year' => '0'], 'year'],
            'year too high' => [['firstname' => 'Maria', 'surname' => 'Santos', 'year' => '11'], 'year'],
            'array smuggling' => [['firstname' => ['Maria'], 'surname' => 'Santos'], 'firstname'],
        ];

        foreach ($invalid as $label => [$payload, $field]) {
            $this->actingAs($student)->from(route('account.edit'))
                ->put(route('account.profile'), $payload)
                ->assertRedirect(route('account.edit'))
                ->assertSessionHasErrors($field, null, 'default');

            $fresh = $student->fresh();
            $this->assertSame('stu', $fresh->firstname, $label);
            $this->assertSame('Test', $fresh->surname, $label);
            $this->assertSame('BSIT', $fresh->course, $label);
        }
    }

    public function test_an_account_with_no_surname_can_still_save_its_profile(): void
    {
        // Mirrors the seeded administrator, whose surname is an empty string.
        $admin = $this->user('adm', Role::Admin, ['firstname' => 'Administrator', 'surname' => '']);

        $this->actingAs($admin)->get(route('account.edit'))->assertOk();

        $this->actingAs($admin)->from(route('account.edit'))
            ->put(route('account.profile'), ['firstname' => 'Head Administrator', 'surname' => ''])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHasNoErrors();

        $fresh = $admin->fresh();
        $this->assertSame('Head Administrator', $fresh->firstname);
        $this->assertSame('', (string) $fresh->surname);
        $this->assertSame('Head Administrator', $fresh->name);
    }

    public function test_user_supplied_values_are_escaped_on_the_page(): void
    {
        $student = $this->user('stu', Role::Student, [
            'firstname' => '<script>alert(1)</script>',
            'course' => '"><img src=x onerror=alert(2)>',
        ]);

        $this->actingAs($student)->get(route('account.edit'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(2)>', false)
            ->assertSee(e('<script>alert(1)</script>'), false);
    }

    public function test_updating_the_profile_does_not_touch_requests(): void
    {
        $student = $this->user('stu', Role::Student);
        $type = \App\Models\DocumentType::create(['name' => 'Good Moral', 'description' => 'd', 'processing_days' => 2, 'fee' => 0, 'is_active' => true]);

        $request = new DocumentRequest();
        $request->forceFill([
            'user_id' => $student->id,
            'document_type_id' => $type->id,
            'status' => \App\Enums\RequestStatus::Pending,
        ])->save();

        $this->actingAs($student)->from(route('account.edit'))
            ->put(route('account.profile'), ['firstname' => 'Maria', 'surname' => 'Santos'])
            ->assertRedirect(route('account.edit'));

        $this->assertSame(1, DocumentRequest::query()->count());
        $this->assertSame(\App\Enums\RequestStatus::Pending, $request->fresh()->status);
    }

    // ---- password -------------------------------------------------------------

    public function test_a_user_can_change_their_password(): void
    {
        $student = $this->user('stu', Role::Student);
        $other = $this->user('other', Role::Student);
        $otherHash = $other->fresh()->password;
        $tokenBefore = $student->fresh()->remember_token;

        $this->actingAs($student)->from(route('account.edit'))
            ->put(route('account.password'), [
                'current_password' => self::OLD_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('status', 'Your password has been changed.')
            ->assertSessionHasNoErrors();

        $fresh = $student->fresh();

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
        $this->assertFalse(Hash::check(self::OLD_PASSWORD, $fresh->password));
        $this->assertNotSame(self::NEW_PASSWORD, $fresh->password, 'the password must be stored hashed');
        $this->assertNotSame($tokenBefore, $fresh->remember_token, 'remember-me tokens on other devices are invalidated');
        $this->assertSame($otherHash, $other->fresh()->password);
    }

    public function test_the_new_password_works_for_login_and_the_old_one_does_not(): void
    {
        $student = $this->user('stu', Role::Student);

        $this->actingAs($student)->from(route('account.edit'))
            ->put(route('account.password'), [
                'current_password' => self::OLD_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertSessionHasNoErrors();

        auth()->logout();

        $this->post(route('login'), ['school_id' => $student->school_id, 'password' => self::OLD_PASSWORD])
            ->assertSessionHasErrors('school_id');
        $this->assertGuest();

        $this->post(route('login'), ['school_id' => $student->school_id, 'password' => self::NEW_PASSWORD])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($student);
    }

    public function test_password_change_validation_rejects_bad_input_and_changes_nothing(): void
    {
        $student = $this->user('stu', Role::Student);
        $hash = $student->fresh()->password;

        $invalid = [
            'wrong current password' => [['current_password' => 'not-my-password', 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD], 'current_password'],
            'missing current password' => [['password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD], 'current_password'],
            'too short' => [['current_password' => self::OLD_PASSWORD, 'password' => 'short-pass1', 'password_confirmation' => 'short-pass1'], 'password'],
            'confirmation mismatch' => [['current_password' => self::OLD_PASSWORD, 'password' => self::NEW_PASSWORD, 'password_confirmation' => 'something-else-entirely'], 'password'],
            'missing confirmation' => [['current_password' => self::OLD_PASSWORD, 'password' => self::NEW_PASSWORD], 'password'],
            'same as current' => [['current_password' => self::OLD_PASSWORD, 'password' => self::OLD_PASSWORD, 'password_confirmation' => self::OLD_PASSWORD], 'password'],
            'empty new password' => [['current_password' => self::OLD_PASSWORD, 'password' => '', 'password_confirmation' => ''], 'password'],
            'array smuggling' => [['current_password' => self::OLD_PASSWORD, 'password' => [self::NEW_PASSWORD], 'password_confirmation' => self::NEW_PASSWORD], 'password'],
        ];

        foreach ($invalid as $label => [$payload, $field]) {
            // The route allows 5 attempts a minute; this loop deliberately sends more, so reset the limiter.
            \Illuminate\Support\Facades\Cache::flush();

            $this->actingAs($student)->from(route('account.edit'))
                ->put(route('account.password'), $payload)
                ->assertRedirect(route('account.edit'))
                ->assertSessionHasErrors($field);

            $this->assertSame($hash, $student->fresh()->password, $label);
        }
    }

    public function test_the_password_form_never_echoes_passwords_back(): void
    {
        $student = $this->user('stu', Role::Student);

        $this->actingAs($student)->from(route('account.edit'))
            ->put(route('account.password'), [
                'current_password' => 'wrong-current-password',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ]);

        // Laravel does not flash password fields; the re-rendered form must not contain them either.
        $this->actingAs($student)->get(route('account.edit'))
            ->assertOk()
            ->assertDontSee('wrong-current-password')
            ->assertDontSee(self::NEW_PASSWORD);
    }

    public function test_password_changes_are_throttled(): void
    {
        $student = $this->user('stu', Role::Student);
        $hash = $student->fresh()->password;

        $attempt = fn () => $this->actingAs($student)->from(route('account.edit'))
            ->put(route('account.password'), [
                'current_password' => 'wrong-current-password',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ]);

        foreach (range(1, 5) as $i) {
            $attempt()->assertRedirect(route('account.edit'));
        }

        $attempt()->assertStatus(429);

        $this->assertSame($hash, $student->fresh()->password);
    }

    // ---- helpers --------------------------------------------------------------

    /** @param  array<string, mixed>  $extra */
    private function user(string $key, Role $role, array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'name' => $key,
            'school_id' => $key.'-001',
            'firstname' => $key,
            'surname' => 'Test',
            'email' => $key.'@example.test',
            'password' => self::OLD_PASSWORD,
            'role' => $role,
            'status' => UserStatus::Activated,
        ], $extra));
    }
}

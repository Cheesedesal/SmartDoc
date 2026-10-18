<?php

namespace Tests\Feature;

use App\Enums\AnnouncementStatus;
use App\Enums\Audience;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['smartdoc.automation.tick_seconds' => 0]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('announcements.index'))->assertRedirect(route('login'));
    }

    public function test_every_role_can_open_the_page(): void
    {
        foreach ([Role::Student, Role::Staff, Role::Admin] as $role) {
            $this->actingAs($this->user('u'.$role->value, $role))
                ->get(route('announcements.index'))
                ->assertOk()
                ->assertSee('Announcements');
        }
    }

    public function test_empty_state_is_shown_when_nothing_is_visible(): void
    {
        $this->actingAs($this->user('s1', Role::Student))
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('No announcements');
    }

    public function test_each_role_only_sees_its_own_audience_and_everyone_notices(): void
    {
        $author = $this->user('author', Role::Admin);

        $this->announce($author, 'For all users', Audience::All);
        $this->announce($author, 'For students only', Audience::Student);
        $this->announce($author, 'For staff only', Audience::Staff);
        $this->announce($author, 'For admins only', Audience::Admin);

        $this->actingAs($this->user('s1', Role::Student))->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('For all users')
            ->assertSee('For students only')
            ->assertDontSee('For staff only')
            ->assertDontSee('For admins only');

        $this->actingAs($this->user('st1', Role::Staff))->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('For all users')
            ->assertSee('For staff only')
            ->assertDontSee('For students only')
            ->assertDontSee('For admins only');

        $this->actingAs($author)->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('For all users')
            ->assertSee('For admins only')
            ->assertDontSee('For students only')
            ->assertDontSee('For staff only');
    }

    public function test_drafts_expired_status_and_past_expiry_are_hidden(): void
    {
        $author = $this->user('author', Role::Admin);

        $this->announce($author, 'Visible notice', Audience::All);
        $this->announce($author, 'Draft notice', Audience::All, AnnouncementStatus::Draft);
        $this->announce($author, 'Expired status notice', Audience::All, AnnouncementStatus::Expired);
        $this->announce($author, 'Past expiry notice', Audience::All, AnnouncementStatus::Published, now()->subDays(2));
        $this->announce($author, 'Future expiry notice', Audience::All, AnnouncementStatus::Published, now()->addDays(5));

        $this->actingAs($this->user('s1', Role::Student))->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Visible notice')
            ->assertSee('Future expiry notice')
            ->assertDontSee('Draft notice')
            ->assertDontSee('Expired status notice')
            ->assertDontSee('Past expiry notice');
    }

    public function test_message_html_is_not_rendered(): void
    {
        $author = $this->user('author', Role::Admin);

        $this->announce($author, 'Markup test', Audience::All, AnnouncementStatus::Published, null, '<script>alert("x")</script>Plain text');

        $this->actingAs($this->user('s1', Role::Student))->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Plain text')
            ->assertDontSee('<script>alert("x")</script>', false);
    }

    public function test_the_page_is_paginated(): void
    {
        $author = $this->user('author', Role::Admin);

        for ($i = 1; $i <= 12; $i++) {
            $this->announce($author, 'Notice number '.$i, Audience::All);
        }

        $student = $this->user('s1', Role::Student);

        $this->actingAs($student)->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Page 1 of 2');

        $this->actingAs($student)->get(route('announcements.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Page 2 of 2');
    }

    public function test_dashboard_links_to_the_announcements_page_when_there_are_announcements(): void
    {
        $author = $this->user('author', Role::Admin);
        $this->announce($author, 'Dashboard preview notice', Audience::All);

        $this->actingAs($this->user('s1', Role::Student))->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard preview notice')
            ->assertSee(route('announcements.index'), false);
    }

    // ---- helpers ----------------------------------------------------------

    private function user(string $key, Role $role): User
    {
        return User::factory()->create([
            'name' => $key,
            'school_id' => $key.'-001',
            'firstname' => $key,
            'surname' => 'Test',
            'email' => $key.'@example.test',
            'password' => 'password',
            'role' => $role,
            'status' => UserStatus::Activated,
        ]);
    }

    private function announce(
        User $author,
        string $title,
        Audience $audience,
        AnnouncementStatus $status = AnnouncementStatus::Published,
        $expiresAt = null,
        string $message = 'Body of the notice.'
    ): Announcement {
        return Announcement::create([
            'title' => $title,
            'message' => $message,
            'audience' => $audience,
            'status' => $status,
            'published_at' => now()->subHour(),
            'expires_at' => $expiresAt,
            'created_by' => $author->id,
        ]);
    }
}

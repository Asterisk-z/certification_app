<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function recipientUser(): User
    {
        return User::factory()->create(['role' => UserRole::Recipient]);
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = $this->admin();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('user.email', $user->email);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_bad_credentials(): void
    {
        $user = $this->admin();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        $this->assertGuest();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_guest_browser_navigation_to_protected_url_redirects_to_spa_login(): void
    {
        // A plain (HTML-accepting) navigation to a protected URL must send the
        // guest to the SPA login page, not crash on a non-existent backend
        // `login` route.
        $this->get('/api/auth/me')->assertRedirect('/login');
    }

    public function test_me_returns_current_user(): void
    {
        $user = $this->recipientUser();

        $this->actingAs($user)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.role', 'recipient');
    }

    public function test_logout_clears_session(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->postJson('/api/auth/logout')->assertOk();
    }
}

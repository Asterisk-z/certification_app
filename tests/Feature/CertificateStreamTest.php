<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_changes_returns_signature_that_moves_on_change(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $first = $this->actingAs($admin)->getJson('/api/admin/certificates/changes')
            ->assertOk()
            ->json('signature');

        // No change yet — signature is stable.
        $this->assertEquals(
            $first,
            $this->actingAs($admin)->getJson('/api/admin/certificates/changes')->json('signature')
        );

        Certificate::factory()->sent()->create();

        $this->assertNotEquals(
            $first,
            $this->actingAs($admin)->getJson('/api/admin/certificates/changes')->json('signature')
        );
    }

    public function test_changes_requires_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::Recipient]);

        $this->actingAs($user)->getJson('/api/admin/certificates/changes')->assertForbidden();
    }
}

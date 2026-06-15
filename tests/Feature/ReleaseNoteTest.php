<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ReleaseNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_admin_can_publish_a_version(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/release-notes', [
            'version' => '1.2.0',
            'title' => 'Bulk actions',
            'body' => "- Bulk invite\n- Bulk delete",
            'released_on' => '2026-06-15',
        ])->assertCreated()->assertJsonPath('version', '1.2.0');

        $this->assertDatabaseHas('release_notes', ['version' => '1.2.0', 'created_by' => $this->admin->id]);
    }

    public function test_non_admin_cannot_manage_versions(): void
    {
        $user = User::factory()->create(['role' => UserRole::Recipient]);

        $this->actingAs($user)->postJson('/api/admin/release-notes', [
            'version' => '1.0.0', 'body' => 'x', 'released_on' => '2026-06-15',
        ])->assertForbidden();
    }

    public function test_changelog_is_public_with_current_version_newest_first(): void
    {
        ReleaseNote::factory()->create(['version' => '1.0.0', 'released_on' => '2026-01-01']);
        ReleaseNote::factory()->create(['version' => '2.0.0', 'released_on' => '2026-06-01']);

        $response = $this->getJson('/api/changelog')->assertOk();

        $this->assertEquals('2.0.0', $response->json('current_version'));
        $this->assertEquals('2.0.0', $response->json('notes.0.version'));
        $this->assertEquals('1.0.0', $response->json('notes.1.version'));
    }

    public function test_empty_changelog_returns_null_version(): void
    {
        $this->getJson('/api/changelog')->assertOk()->assertJsonPath('current_version', null);
    }

    public function test_admin_can_update_and_delete_a_version(): void
    {
        $note = ReleaseNote::factory()->create(['version' => '1.0.0']);

        $this->actingAs($this->admin)->putJson("/api/admin/release-notes/{$note->uuid}", [
            'version' => '1.0.1', 'body' => 'Patch', 'released_on' => '2026-06-15',
        ])->assertOk()->assertJsonPath('version', '1.0.1');

        $this->actingAs($this->admin)->deleteJson("/api/admin/release-notes/{$note->uuid}")->assertOk();
        $this->assertDatabaseMissing('release_notes', ['id' => $note->id]);
    }
}

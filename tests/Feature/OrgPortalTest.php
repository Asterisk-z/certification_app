<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class OrgPortalTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $userA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();
        $this->userA = User::factory()->create([
            'role' => UserRole::Organization,
            'organization_id' => $this->orgA->id,
        ]);
    }

    private function template(Organization $org): CertificateTemplate
    {
        return CertificateTemplate::factory()->ready()->create(['organization_id' => $org->id]);
    }

    public function test_org_listing_shows_only_its_own_records(): void
    {
        $this->template($this->orgA);
        $this->template($this->orgB);
        CertificateTemplate::factory()->create(['organization_id' => null]); // admin

        $this->actingAs($this->userA)->getJson('/api/org/templates')
            ->assertOk()->assertJsonPath('total', 1);
    }

    public function test_org_cannot_read_or_mutate_another_orgs_template(): void
    {
        $foreign = $this->template($this->orgB);

        $this->actingAs($this->userA)->getJson("/api/org/templates/{$foreign->uuid}")->assertNotFound();
        $this->actingAs($this->userA)->putJson("/api/org/templates/{$foreign->uuid}", [
            'name' => 'Hijacked', 'code' => 'HJK',
        ])->assertNotFound();
        $this->actingAs($this->userA)->deleteJson("/api/org/templates/{$foreign->uuid}")->assertNotFound();
    }

    public function test_org_cannot_read_another_orgs_recipient_group_or_certificate(): void
    {
        $recipient = Recipient::factory()->create(['organization_id' => $this->orgB->id]);
        $group = Group::factory()->create(['organization_id' => $this->orgB->id]);
        $certificate = Certificate::factory()->create([
            'organization_id' => $this->orgB->id,
            'certificate_template_id' => $this->template($this->orgB)->id,
        ]);

        $this->actingAs($this->userA)->getJson("/api/org/recipients/{$recipient->uuid}")->assertNotFound();
        $this->actingAs($this->userA)->getJson("/api/org/groups/{$group->uuid}")->assertNotFound();
        $this->actingAs($this->userA)->getJson("/api/org/certificates/{$certificate->uuid}")->assertNotFound();
    }

    public function test_org_cannot_manual_issue_with_a_foreign_template(): void
    {
        $foreignTemplate = $this->template($this->orgB);
        $recipient = Recipient::factory()->create(['organization_id' => $this->orgA->id]);

        $this->actingAs($this->userA)->postJson('/api/org/certificates/manual', [
            'template_uuid' => $foreignTemplate->uuid,
            'recipient_uuid' => $recipient->uuid,
            'issue_date' => '2026-06-01',
        ])->assertNotFound();

        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_org_can_create_its_own_template_scoped_to_it(): void
    {
        $response = $this->actingAs($this->userA)->postJson('/api/org/templates', [
            'name' => 'Org A Course',
            'code' => 'AAA',
            'duration' => 1,
            'duration_type' => 'year',
            'background' => UploadedFile::fake()->image('bg.png', 1000, 700),
        ])->assertCreated();

        $template = CertificateTemplate::firstWhere('uuid', $response->json('uuid'));
        $this->assertSame($this->orgA->id, $template->organization_id);
    }

    public function test_admin_and_recipient_cannot_use_the_org_portal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $recipient = User::factory()->create(['role' => UserRole::Recipient]);

        $this->actingAs($admin)->getJson('/api/org/templates')->assertForbidden();
        $this->actingAs($recipient)->getJson('/api/org/templates')->assertForbidden();
    }

    public function test_inactive_org_is_blocked_from_the_portal(): void
    {
        $this->orgA->update(['status' => 'inactive']);

        $this->actingAs($this->userA)->getJson('/api/org/templates')->assertForbidden();
    }

    public function test_active_org_logs_in_and_receives_its_organization(): void
    {
        User::factory()->create([
            'role' => UserRole::Organization,
            'organization_id' => $this->orgA->id,
            'email' => 'login@orga.test',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'login@orga.test',
            'password' => 'secret-password',
        ])->assertOk()->assertJsonPath('user.organization.uuid', $this->orgA->uuid);
    }

    public function test_inactive_org_cannot_log_in(): void
    {
        $this->orgA->update(['status' => 'inactive']);
        User::factory()->create([
            'role' => UserRole::Organization,
            'organization_id' => $this->orgA->id,
            'email' => 'login@orga.test',
            'password' => 'secret-password',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'login@orga.test',
            'password' => 'secret-password',
        ])->assertStatus(422);

        $this->assertGuest();
    }

    public function test_org_dashboard_counts_only_its_own_data(): void
    {
        $this->template($this->orgA);
        $this->template($this->orgB);
        CertificateTemplate::factory()->create(['organization_id' => null]);

        $this->actingAs($this->userA)->getJson('/api/org/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('totals.templates', 1)
            ->assertJsonPath('recent_activity', []);
    }

    public function test_template_code_is_unique_per_org_but_reusable_across_orgs(): void
    {
        $userB = User::factory()->create([
            'role' => UserRole::Organization,
            'organization_id' => $this->orgB->id,
        ]);
        $bg = fn () => UploadedFile::fake()->image('bg.png', 1000, 700);

        // Org A takes code DUP.
        $this->actingAs($this->userA)->postJson('/api/org/templates', [
            'name' => 'A', 'code' => 'DUP', 'background' => $bg(),
        ])->assertCreated();

        // Org A cannot reuse it…
        $this->actingAs($this->userA)->postJson('/api/org/templates', [
            'name' => 'A2', 'code' => 'DUP', 'background' => $bg(),
        ])->assertJsonValidationErrors('code');

        // …but org B can.
        $this->actingAs($userB)->postJson('/api/org/templates', [
            'name' => 'B', 'code' => 'DUP', 'background' => $bg(),
        ])->assertCreated();
    }
}

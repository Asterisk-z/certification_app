<?php

namespace Tests\Feature;

use App\Enums\CertificateStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OrganizationLimitTest extends TestCase
{
    use RefreshDatabase;

    private function org(array $limits): Organization
    {
        return Organization::factory()->create(['limits' => $limits]);
    }

    private function orgUser(Organization $org): User
    {
        return User::factory()->create(['role' => UserRole::Organization, 'organization_id' => $org->id]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Admin]);
    }

    private function template(Organization $org): CertificateTemplate
    {
        return CertificateTemplate::factory()->ready()->create(['organization_id' => $org->id]);
    }

    private function activeCert(Organization $org, CertificateTemplate $template, Recipient $recipient, ?Group $group = null): Certificate
    {
        return Certificate::factory()->create([
            'organization_id' => $org->id,
            'certificate_template_id' => $template->id,
            'recipient_id' => $recipient->id,
            'group_id' => $group?->id,
            'status' => CertificateStatus::Sent,
        ]);
    }

    // ---- total caps ---------------------------------------------------------

    public function test_recipients_cap_blocks_org_at_the_limit(): void
    {
        $org = $this->org(['recipients' => 1]);
        $user = $this->orgUser($org);
        Recipient::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($user)->postJson('/api/org/recipients', [
            'full_name' => 'Over Limit', 'email' => 'over@e.com',
        ])->assertStatus(422)->assertJsonValidationErrors('recipients');

        $this->assertDatabaseMissing('recipients', ['email' => 'over@e.com']);
    }

    public function test_null_limits_are_unlimited(): void
    {
        $org = $this->org([]);
        $user = $this->orgUser($org);
        Recipient::factory()->count(3)->create(['organization_id' => $org->id]);

        $this->actingAs($user)->postJson('/api/org/recipients', [
            'full_name' => 'Fine', 'email' => 'fine@e.com',
        ])->assertCreated();
    }

    public function test_templates_cap_blocks_org_at_the_limit(): void
    {
        $org = $this->org(['templates' => 1]);
        $user = $this->orgUser($org);
        CertificateTemplate::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($user)->postJson('/api/org/templates', [
            'name' => 'Second', 'code' => 'SEC',
            'background' => UploadedFile::fake()->image('bg.png', 1000, 700),
        ])->assertStatus(422)->assertJsonValidationErrors('templates');
    }

    public function test_groups_cap_blocks_org_at_the_limit(): void
    {
        $org = $this->org(['groups' => 1]);
        $user = $this->orgUser($org);
        Group::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($user)->postJson('/api/org/groups', ['name' => 'Second group'])
            ->assertStatus(422)->assertJsonValidationErrors('groups');
    }

    public function test_certificates_total_cap_blocks_send(): void
    {
        $org = $this->org(['certificates' => 1]);
        $user = $this->orgUser($org);
        $template = $this->template($org);
        $r1 = Recipient::factory()->create(['organization_id' => $org->id]);
        $this->activeCert($org, $template, $r1);
        $r2 = Recipient::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($user)->postJson("/api/org/templates/{$template->uuid}/send", [
            'recipient_uuids' => [$r2->uuid],
            'completion_date' => '2026-06-01',
            'issue_date' => '2026-06-01',
        ])->assertStatus(422)->assertJsonValidationErrors('certificates');
    }

    // ---- scoped caps --------------------------------------------------------

    public function test_certificates_per_recipient_cap_blocks_manual_issue(): void
    {
        $org = $this->org(['certificates_per_recipient' => 1]);
        $user = $this->orgUser($org);
        $template = $this->template($org);
        $recipient = Recipient::factory()->create(['organization_id' => $org->id]);
        $this->activeCert($org, $template, $recipient);

        $this->actingAs($user)->postJson('/api/org/certificates/manual', [
            'template_uuid' => $template->uuid,
            'recipient_uuid' => $recipient->uuid,
            'issue_date' => '2026-06-01',
        ])->assertStatus(422)->assertJsonValidationErrors('recipient');
    }

    public function test_groups_per_recipient_cap_blocks_and_rolls_back(): void
    {
        $org = $this->org(['groups_per_recipient' => 1]);
        $user = $this->orgUser($org);
        $g1 = Group::factory()->create(['organization_id' => $org->id]);
        $g2 = Group::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($user)->postJson('/api/org/recipients', [
            'full_name' => 'Multi', 'email' => 'multi@e.com',
            'group_uuids' => [$g1->uuid, $g2->uuid],
        ])->assertStatus(422)->assertJsonValidationErrors('group_uuids');

        // The transaction rolled back: no dangling recipient.
        $this->assertDatabaseMissing('recipients', ['email' => 'multi@e.com']);
    }

    public function test_renewal_is_not_blocked_by_per_recipient_cap(): void
    {
        $org = $this->org(['certificates_per_recipient' => 1]);
        $user = $this->orgUser($org);
        $template = $this->template($org);
        $recipient = Recipient::factory()->create(['organization_id' => $org->id]);
        $cert = Certificate::factory()->create([
            'organization_id' => $org->id,
            'certificate_template_id' => $template->id,
            'recipient_id' => $recipient->id,
            'status' => CertificateStatus::Sent,
            'completion_date' => '2026-01-01',
            'issue_date' => '2026-01-01',
        ]);

        // Renewing supersedes the old credential (net-zero), so the cap allows it.
        $this->actingAs($user)->postJson("/api/org/certificates/{$cert->uuid}/renew", [
            'issue_date' => '2026-06-10',
        ])->assertCreated();
    }

    // ---- admin bypass -------------------------------------------------------

    public function test_admin_bypasses_certificate_admin_cap_when_inviting(): void
    {
        Mail::fake();
        $org = $this->org(['certificate_admins' => 1]);
        $this->orgUser($org); // org already at its cap
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/api/admin/organizations/{$org->uuid}/team", [
            'name' => 'Extra Admin', 'email' => 'extra@e.com',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'extra@e.com',
            'role' => UserRole::Organization->value,
            'organization_id' => $org->id,
        ]);
    }

    // ---- certificate-admin team --------------------------------------------

    public function test_org_cannot_invite_beyond_certificate_admin_cap(): void
    {
        Mail::fake();
        $org = $this->org(['certificate_admins' => 1]);
        $primary = $this->orgUser($org); // at cap

        $this->actingAs($primary)->postJson('/api/org/team', [
            'name' => 'Teammate', 'email' => 'mate@e.com',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'mate@e.com']);
    }

    public function test_org_can_invite_within_cap(): void
    {
        Mail::fake();
        $org = $this->org(['certificate_admins' => 2]);
        $primary = $this->orgUser($org);

        $this->actingAs($primary)->postJson('/api/org/team', [
            'name' => 'Teammate', 'email' => 'mate@e.com',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'mate@e.com', 'organization_id' => $org->id]);
    }

    public function test_primary_org_login_cannot_be_removed(): void
    {
        $org = $this->org([]);
        $primary = $this->orgUser($org);

        $this->actingAs($primary)->deleteJson("/api/org/team/{$primary->uuid}")
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $primary->id, 'deleted_at' => null]);
    }

    // ---- admin configures limits -------------------------------------------

    public function test_admin_can_set_and_clear_limits_via_settings(): void
    {
        $org = $this->org(['recipients' => 9]);
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson("/api/admin/organizations/{$org->uuid}/settings", [
            'limits' => ['recipients' => 5, 'certificates_per_group' => ''],
        ])->assertOk();

        $org->refresh();
        $this->assertSame(5, $org->limits['recipients']);
        $this->assertArrayNotHasKey('certificates_per_group', $org->limits ?? []);
    }

    public function test_limits_reject_non_positive_values(): void
    {
        $org = $this->org([]);
        $admin = $this->admin();

        $this->actingAs($admin)->patchJson("/api/admin/organizations/{$org->uuid}/settings", [
            'limits' => ['recipients' => 0],
        ])->assertStatus(422)->assertJsonValidationErrors('limits.recipients');
    }
}

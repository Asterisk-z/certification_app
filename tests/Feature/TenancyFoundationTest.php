<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Organization;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function orgUser(Organization $org): User
    {
        return User::factory()->create([
            'role' => UserRole::Organization,
            'organization_id' => $org->id,
        ]);
    }

    public function test_org_user_only_sees_its_own_records(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        CertificateTemplate::factory()->create(['organization_id' => $orgA->id]);
        CertificateTemplate::factory()->create(['organization_id' => $orgB->id]);
        CertificateTemplate::factory()->create(['organization_id' => null]); // admin-owned

        $this->actingAs($this->orgUser($orgA));

        $this->assertSame(1, CertificateTemplate::count());
    }

    public function test_admin_and_guest_see_every_org(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        CertificateTemplate::factory()->create(['organization_id' => $orgA->id]);
        CertificateTemplate::factory()->create(['organization_id' => $orgB->id]);
        CertificateTemplate::factory()->create(['organization_id' => null]);

        // Guest (queue / public verifier) — unscoped.
        $this->assertSame(3, CertificateTemplate::count());

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->assertSame(3, CertificateTemplate::count());
    }

    public function test_creating_inherits_org_from_authenticated_org_user(): void
    {
        $org = Organization::factory()->create();
        $this->actingAs($this->orgUser($org));

        // Factory passes organization_id => null; the creating hook fills it.
        $template = CertificateTemplate::factory()->create(['organization_id' => null]);

        $this->assertSame($org->id, $template->fresh()->organization_id);
    }

    public function test_records_created_without_an_org_user_are_admin_owned(): void
    {
        $this->assertNull(CertificateTemplate::factory()->create()->organization_id);
        $this->assertNull(Recipient::factory()->create()->organization_id);
    }

    public function test_recipient_email_is_reusable_across_organizations(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        Recipient::factory()->create(['organization_id' => $orgA->id, 'email' => 'same@example.com']);
        Recipient::factory()->create(['organization_id' => $orgB->id, 'email' => 'same@example.com']);

        $this->assertDatabaseCount('recipients', 2);
    }

    public function test_certificate_number_uniqueness_is_checked_across_all_orgs(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        Certificate::factory()->create([
            'organization_id' => $orgA->id,
            'certificate_number' => 'GLOB-AAAAAAAA',
        ]);

        $this->actingAs($this->orgUser($orgB));

        // The scoped query cannot see org A's certificate…
        $this->assertFalse(Certificate::where('certificate_number', 'GLOB-AAAAAAAA')->exists());
        // …but the number service escapes the scope, so the number is "taken".
        $this->assertTrue(
            Certificate::withoutGlobalScope(BelongsToOrganization::class)
                ->where('certificate_number', 'GLOB-AAAAAAAA')
                ->exists()
        );
    }
}

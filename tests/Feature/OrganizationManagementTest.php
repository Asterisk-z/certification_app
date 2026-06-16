<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\OrganizationWelcomeMail;
use App\Models\CertificateTemplate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_non_admin_cannot_manage_organizations(): void
    {
        $org = User::factory()->create(['role' => UserRole::Organization]);

        $this->actingAs($org)->getJson('/api/admin/organizations')->assertForbidden();
    }

    public function test_admin_creates_org_with_initial_password(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)->postJson('/api/admin/organizations', [
            'name' => 'Acme Safety',
            'email' => 'ops@acme.test',
            'status' => 'active',
            'provision' => 'password',
            'password' => 'secret-password',
        ])->assertCreated()->assertJsonPath('name', 'Acme Safety');

        $org = Organization::firstWhere('uuid', $response->json('uuid'));
        $user = $org->users()->where('role', UserRole::Organization)->first();
        $this->assertNotNull($user);
        $this->assertSame('ops@acme.test', $user->email);
        // Password is set, so the org can log in immediately.
        $this->assertTrue(Hash::check('secret-password', $user->password));

        // A welcome mail goes out even when no setup link is involved.
        Mail::assertQueued(
            OrganizationWelcomeMail::class,
            fn (OrganizationWelcomeMail $mail) => $mail->hasTo('ops@acme.test') && ! $mail->setupLinkSent,
        );
        $this->assertDatabaseHas('mail_logs', [
            'recipient_email' => 'ops@acme.test',
            'mailable_type' => OrganizationWelcomeMail::class,
        ]);
    }

    public function test_admin_creates_org_with_setup_link(): void
    {
        Notification::fake();
        Mail::fake();

        $this->actingAs($this->admin)->postJson('/api/admin/organizations', [
            'name' => 'Beta Corp',
            'email' => 'admin@beta.test',
            'provision' => 'link',
        ])->assertCreated();

        $user = User::where('email', 'admin@beta.test')->first();
        Notification::assertSentTo($user, ResetPassword::class);

        // The welcome mail is sent here too, flagged that a setup link follows.
        Mail::assertQueued(
            OrganizationWelcomeMail::class,
            fn (OrganizationWelcomeMail $mail) => $mail->hasTo('admin@beta.test') && $mail->setupLinkSent,
        );
    }

    public function test_org_email_must_be_unique_against_orgs_and_users(): void
    {
        User::factory()->create(['email' => 'taken@x.test']);

        $this->actingAs($this->admin)->postJson('/api/admin/organizations', [
            'name' => 'Clash',
            'email' => 'taken@x.test',
            'provision' => 'link',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_admin_can_filter_a_listing_by_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        CertificateTemplate::factory()->create(['organization_id' => $orgA->id]);
        CertificateTemplate::factory()->create(['organization_id' => $orgB->id]);
        CertificateTemplate::factory()->create(['organization_id' => null]);

        // Unfiltered: admin sees everything.
        $this->actingAs($this->admin)->getJson('/api/admin/templates')
            ->assertOk()->assertJsonPath('total', 3);

        // Filtered to org A.
        $this->actingAs($this->admin)->getJson("/api/admin/templates?organization={$orgA->uuid}")
            ->assertOk()->assertJsonPath('total', 1);

        // Unknown org → 404.
        $this->actingAs($this->admin)->getJson('/api/admin/templates?organization='.fake()->uuid())
            ->assertNotFound();
    }

    public function test_admin_can_toggle_status_and_features_via_settings(): void
    {
        $org = Organization::factory()->create(['status' => 'active', 'features' => null]);

        $this->actingAs($this->admin)->patchJson("/api/admin/organizations/{$org->uuid}/settings", [
            'status' => 'inactive',
        ])->assertOk()->assertJsonPath('status', 'inactive');

        $this->actingAs($this->admin)->patchJson("/api/admin/organizations/{$org->uuid}/settings", [
            'features' => ['templates' => false],
        ])->assertOk();

        $this->assertFalse($org->fresh()->allows('templates'));
        $this->assertTrue($org->fresh()->allows('recipients'));
    }

    public function test_admin_sees_org_scoped_stats(): void
    {
        $org = Organization::factory()->create();
        CertificateTemplate::factory()->count(2)->create(['organization_id' => $org->id]);
        CertificateTemplate::factory()->create(['organization_id' => null]); // admin-owned, excluded

        $this->actingAs($this->admin)->getJson("/api/admin/organizations/{$org->uuid}/stats")
            ->assertOk()
            ->assertJsonPath('totals.templates', 2);
    }

    public function test_admin_can_update_status_and_delete(): void
    {
        $org = Organization::factory()->create(['status' => 'active']);

        $this->actingAs($this->admin)->putJson("/api/admin/organizations/{$org->uuid}", [
            'name' => $org->name,
            'email' => $org->email,
            'status' => 'inactive',
        ])->assertOk()->assertJsonPath('status', 'inactive');

        $this->actingAs($this->admin)->deleteJson("/api/admin/organizations/{$org->uuid}")->assertOk();
        $this->assertSoftDeleted('organizations', ['id' => $org->id]);
    }
}

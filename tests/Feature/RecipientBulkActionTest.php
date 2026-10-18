<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\RecipientInviteMail;
use App\Models\MailLog;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecipientBulkActionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_bulk_invite_emails_each_selected_recipient(): void
    {
        Mail::fake();
        $recipients = Recipient::factory()->count(3)->create();

        $this->actingAs($this->admin)->postJson('/api/admin/recipients/bulk-action', [
            'action' => 'invite',
            'uuids' => $recipients->pluck('uuid')->all(),
        ])->assertOk()->assertJsonPath('affected', 3);

        Mail::assertSent(RecipientInviteMail::class, 3);
        $this->assertEquals(3, MailLog::where('mailable_type', RecipientInviteMail::class)->count());
    }

    public function test_bulk_delete_soft_deletes_selected_recipients(): void
    {
        $recipients = Recipient::factory()->count(3)->create();

        $this->actingAs($this->admin)->postJson('/api/admin/recipients/bulk-action', [
            'action' => 'delete',
            'uuids' => $recipients->pluck('uuid')->all(),
        ])->assertOk()->assertJsonPath('affected', 3);

        $this->assertEquals(0, Recipient::count());
        $this->assertEquals(3, Recipient::withTrashed()->count());
    }

    public function test_bulk_action_on_all_matching_filters_spans_every_page(): void
    {
        Mail::fake();
        Recipient::factory()->count(3)->sequence(
            ['full_name' => 'Zed One'], ['full_name' => 'Zed Two'], ['full_name' => 'Zed Three'],
        )->create();
        Recipient::factory()->count(2)->create(['full_name' => 'Someone Else']);

        $this->actingAs($this->admin)->postJson('/api/admin/recipients/bulk-action', [
            'action' => 'invite',
            'all' => true,
            'filters' => ['name' => 'zed', 'portal' => 'not_invited'],
        ])->assertOk()->assertJsonPath('affected', 3);

        Mail::assertSent(RecipientInviteMail::class, 3);

        // Deleting "all matching" removes only the filtered set, even without a uuid list.
        $this->actingAs($this->admin)->postJson('/api/admin/recipients/bulk-action', [
            'action' => 'delete',
            'all' => true,
            'filters' => ['name' => 'zed'],
        ])->assertOk()->assertJsonPath('affected', 3);

        $this->assertEquals(2, Recipient::count());
        $this->assertSame(['Someone Else', 'Someone Else'], Recipient::pluck('full_name')->all());
    }

    public function test_bulk_action_without_all_still_requires_uuids(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/recipients/bulk-action', [
            'action' => 'delete',
            'filters' => ['name' => 'zed'],
        ])->assertUnprocessable()->assertJsonValidationErrors('uuids');
    }

    public function test_bulk_action_validates_action_and_uuids(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/recipients/bulk-action', [
            'action' => 'explode',
            'uuids' => [],
        ])->assertUnprocessable();
    }

    public function test_bulk_action_requires_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::Recipient]);

        $this->actingAs($user)->postJson('/api/admin/recipients/bulk-action', [
            'action' => 'delete',
            'uuids' => [Recipient::factory()->create()->uuid],
        ])->assertForbidden();
    }
}

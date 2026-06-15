<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\RecipientInviteMail;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RecipientInviteOnCreateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_adding_a_recipient_sends_an_invite(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)->postJson('/api/admin/recipients', [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ])->assertCreated();

        Mail::assertQueued(RecipientInviteMail::class, 1);
        $this->assertDatabaseHas('mail_logs', [
            'recipient_email' => 'jane@example.com',
            'mailable_type' => RecipientInviteMail::class,
        ]);
    }

    public function test_bulk_add_invites_only_newly_created_recipients(): void
    {
        Mail::fake();
        // One already exists — it should be updated, not re-invited.
        Recipient::factory()->create(['email' => 'jane@example.com', 'full_name' => 'Old Name']);

        $this->actingAs($this->admin)->postJson('/api/admin/recipients/bulk', [
            'text' => "Jane Doe, jane@example.com\nJohn Smith, john@example.com\nMary Sue, mary@example.com",
        ])->assertOk()
            ->assertJsonPath('created', 2)
            ->assertJsonPath('updated', 1);

        // Only the two new recipients are invited; the existing one is not.
        Mail::assertQueued(RecipientInviteMail::class, 2);
        Mail::assertNotQueued(function (RecipientInviteMail $mail) {
            return $mail->recipient->email === 'jane@example.com';
        });
    }
}

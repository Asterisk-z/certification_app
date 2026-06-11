<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateStreamTest extends TestCase
{
    use RefreshDatabase;

    public function test_stream_emits_sse_with_heartbeat(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/api/admin/certificates/stream');

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertEquals('no', $response->headers->get('X-Accel-Buffering'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('retry: 3000', $content);
        $this->assertStringContainsString(': heartbeat', $content);
    }

    public function test_stream_requires_admin(): void
    {
        $user = User::factory()->create(['role' => UserRole::Recipient]);

        $this->actingAs($user)->get('/api/admin/certificates/stream')->assertForbidden();
    }
}

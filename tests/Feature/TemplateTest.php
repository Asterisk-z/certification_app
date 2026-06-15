<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CertificateTemplate;
use App\Models\User;
use App\Services\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_non_admin_cannot_access_templates(): void
    {
        $user = User::factory()->create(['role' => UserRole::Recipient]);

        $this->actingAs($user)->getJson('/api/admin/templates')->assertForbidden();
    }

    public function test_create_template_seeds_default_blocks_and_captures_dimensions(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/admin/templates', [
            'name' => 'Safety Training',
            'code' => 'hse',
            'duration' => 2,
            'duration_type' => 'year',
            'background' => UploadedFile::fake()->image('bg.png', 1200, 850),
        ]);

        $response->assertCreated()
            ->assertJsonPath('code', 'HSE')
            ->assertJsonPath('bg_width', 1200)
            ->assertJsonPath('bg_height', 850)
            ->assertJsonPath('status', 'draft');

        $template = CertificateTemplate::first();
        $this->assertCount(count(CertificateTemplate::DEFAULT_BLOCKS), $template->blocks);
        $this->assertTrue($template->blocks->every(fn ($b) => $b->is_default && $b->is_dynamic));
        Storage::disk('public')->assertExists($template->background_image);
    }

    public function test_template_without_background_seeds_blocks_inside_canvas(): void
    {
        // Created without an uploaded background, bg dimensions come from the
        // DB defaults — block positions must still land inside the canvas.
        $template = app(TemplateService::class)->create($this->admin, [
            'name' => 'No Background',
            'code' => 'NOBG',
        ]);

        foreach ($template->blocks as $block) {
            $this->assertGreaterThanOrEqual(0, $block->pos_x, "{$block->slug} is positioned off-canvas");
            $this->assertEquals(
                $block->type->value === 'qrcode' ? 120 : CertificateTemplate::DEFAULT_BLOCK_WIDTH,
                $block->width
            );
            $this->assertEquals(CertificateTemplate::DEFAULT_BLOCK_FONT_SIZE, $block->font_size);
        }
    }

    public function test_save_layout_marks_template_ready(): void
    {
        $template = CertificateTemplate::factory()->create(['user_id' => $this->admin->id]);
        $block = $template->blocks()->create([
            'name' => 'Full Name', 'slug' => 'full_name', 'type' => 'text',
            'value' => '{{full_name}}', 'is_dynamic' => true, 'is_default' => true,
        ]);

        $this->actingAs($this->admin)->putJson("/api/admin/templates/{$template->uuid}/layout", [
            'blocks' => [
                ['uuid' => $block->uuid, 'pos_x' => 100.5, 'pos_y' => 220, 'width' => 320, 'height' => 50],
            ],
        ])->assertOk()->assertJsonPath('status', 'ready');

        $block->refresh();
        $this->assertEquals(100.5, $block->pos_x);
        $this->assertEquals(220, $block->pos_y);
    }

    public function test_layout_rejects_positions_outside_canvas(): void
    {
        $template = CertificateTemplate::factory()->create(['user_id' => $this->admin->id, 'bg_width' => 1000]);
        $block = $template->blocks()->create(['name' => 'X', 'slug' => 'x', 'type' => 'text']);

        $this->actingAs($this->admin)->putJson("/api/admin/templates/{$template->uuid}/layout", [
            'blocks' => [['uuid' => $block->uuid, 'pos_x' => 2000, 'pos_y' => 10]],
        ])->assertUnprocessable();
    }

    public function test_duplicate_template_copies_blocks_with_new_code(): void
    {
        $template = CertificateTemplate::factory()->create(['user_id' => $this->admin->id, 'code' => 'ABC']);
        $template->blocks()->createMany([
            ['name' => 'A', 'slug' => 'a', 'type' => 'text'],
            ['name' => 'B', 'slug' => 'b', 'type' => 'text'],
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/templates/{$template->uuid}/duplicate")
            ->assertCreated();

        $this->assertEquals('ABC-COPY', $response->json('code'));
        $this->assertCount(2, $response->json('blocks'));
    }

    public function test_default_block_cannot_be_deleted(): void
    {
        $template = CertificateTemplate::factory()->create(['user_id' => $this->admin->id]);
        $block = $template->blocks()->create([
            'name' => 'Full Name', 'slug' => 'full_name', 'type' => 'text', 'is_default' => true,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/admin/blocks/{$block->uuid}")
            ->assertUnprocessable();
    }

    public function test_admin_can_add_a_signature_block_drawn_on_the_platform(): void
    {
        $template = CertificateTemplate::factory()->create(['user_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->postJson("/api/admin/templates/{$template->uuid}/blocks", [
            'name' => 'Director Signature',
            'type' => 'signature',
            'image' => UploadedFile::fake()->image('signature.png', 600, 200),
            'pos_x' => 40,
            'pos_y' => 40,
            'width' => 300,
            'height' => 100,
        ]);

        $response->assertCreated()
            ->assertJsonPath('type', 'signature')
            ->assertJsonPath('is_dynamic', false);

        $block = $template->blocks()->where('slug', 'director_signature')->first();
        $this->assertNotNull($block->value);
        Storage::disk('public')->assertExists($block->value);
    }

    public function test_redrawing_a_signature_replaces_the_stored_image(): void
    {
        $template = CertificateTemplate::factory()->create(['user_id' => $this->admin->id]);
        $original = UploadedFile::fake()->image('sig.png')->store('blocks', 'public');
        $block = $template->blocks()->create([
            'name' => 'Signature', 'slug' => 'signature', 'type' => 'signature', 'value' => $original,
        ]);

        $this->actingAs($this->admin)->postJson("/api/admin/blocks/{$block->uuid}", [
            '_method' => 'PUT',
            'name' => 'Signature',
            'type' => 'signature',
            'image' => UploadedFile::fake()->image('new-sig.png', 600, 200),
        ])->assertOk();

        $block->refresh();
        $this->assertNotEquals($original, $block->value);
        Storage::disk('public')->assertMissing($original);
        Storage::disk('public')->assertExists($block->value);
    }
}

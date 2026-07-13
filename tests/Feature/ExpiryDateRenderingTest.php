<?php

namespace Tests\Feature;

use App\Exports\ImportFormatExport;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Recipient;
use App\Services\PlaceholderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpiryDateRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiry_date_column_wins_over_a_leaked_data_value(): void
    {
        $recipient = Recipient::factory()->create();
        $certificate = Certificate::factory()->create([
            'recipient_id' => $recipient->id,
            'issue_date' => '2026-06-18',
            'expiry_date' => '2027-06-18',
            // A raw Excel serial (18 Jun 2026) leaked in by an older import.
            'data' => ['expiry_date' => '46191', 'course_title' => 'Fire Safety'],
        ]);

        $values = app(PlaceholderResolver::class)->resolve($certificate);

        $this->assertSame('18 Jun 2027', $values['expiry_date']);
        $this->assertSame('Fire Safety', $values['course_title']);
    }

    public function test_import_format_never_emits_an_expiry_date_column(): void
    {
        $template = CertificateTemplate::factory()->create();
        $template->blocks()->create([
            'name' => 'Expiry Date', 'slug' => 'expiry_date', 'type' => 'text',
            'value' => '{{expiry_date}}', 'is_dynamic' => true,
        ]);

        $this->assertNotContains('expiry_date', ImportFormatExport::headingsFor($template));
    }

    public function test_fix_command_restores_expiry_and_strips_leaked_data(): void
    {
        $recipient = Recipient::factory()->create();
        $certificate = Certificate::factory()->create([
            'recipient_id' => $recipient->id,
            'issue_date' => '2026-06-18',
            'expiry_date' => null,
            'data' => ['expiry_date' => '46191', 'course_title' => 'Fire Safety'],
            'pdf_path' => 'certificates/stale.pdf',
        ]);

        $this->artisan('certificates:fix-date-data')->assertSuccessful();

        $certificate->refresh();
        $this->assertSame('18 Jun 2026', $certificate->expiry_date->format('d M Y'));
        $this->assertArrayNotHasKey('expiry_date', $certificate->data);
        $this->assertSame('Fire Safety', $certificate->data['course_title']);
        $this->assertNull($certificate->pdf_path);
    }
}

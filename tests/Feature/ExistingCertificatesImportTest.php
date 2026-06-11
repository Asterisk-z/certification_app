<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ExistingCertificatesImportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CertificateTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->template = CertificateTemplate::factory()->create([
            'user_id' => $this->admin->id,
            'code' => 'LEG',
            'duration' => 1,
            'duration_type' => 'year',
        ]);
    }

    private function xlsx(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $r => $cells) {
            foreach (array_values($cells) as $c => $value) {
                $sheet->setCellValue([$c + 1, $r + 1], $value);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'legacy').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'legacy.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_existing_format_download(): void
    {
        $this->actingAs($this->admin)
            ->get("/api/admin/templates/{$this->template->uuid}/import-format?mode=existing")
            ->assertOk()
            ->assertDownload('leg-existing-certificates-format.xlsx');
    }

    public function test_registers_offline_certificates_with_original_numbers(): void
    {
        $file = $this->xlsx([
            ['certificate_number', 'full_name', 'email', 'completion_date', 'issue_date', 'expiry_date'],
            ['HSE/2023/0042', 'Jane Doe', 'jane@example.com', '2023-04-01', '2023-05-01', '2099-05-01'],
            ['HSE/2019/0007', 'John Smith', 'john@example.com', '', '2019-02-01', '2020-02-01'],
            ['HSE/2024/0100', 'No Email', 'broken', '', '2024-01-01', ''],
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/admin/templates/{$this->template->uuid}/import",
            ['file' => $file, 'mode' => 'existing']
        );

        $response->assertOk()->assertJsonPath('created', 2);
        $this->assertCount(1, $response->json('failures'));

        // Original number kept, valid one is "sent" and publicly verifiable.
        $valid = Certificate::firstWhere('certificate_number', 'HSE/2023/0042');
        $this->assertEquals('sent', $valid->status->value);
        $this->assertTrue($valid->is_manual);
        $this->assertEquals('2099-05-01', $valid->expiry_date->format('Y-m-d'));

        $this->getJson('/api/verify?number='.urlencode('HSE/2023/0042'))
            ->assertOk()->assertJsonPath('result', 'valid');

        // Long-expired one lands directly as expired.
        $expired = Certificate::firstWhere('certificate_number', 'HSE/2019/0007');
        $this->assertEquals('expired', $expired->status->value);

        $this->assertEquals(2, Recipient::count());
    }

    public function test_duplicate_numbers_are_skipped_so_reimports_are_safe(): void
    {
        Certificate::factory()->sent()->create([
            'certificate_template_id' => $this->template->id,
            'certificate_number' => 'HSE/2023/0042',
        ]);

        $file = $this->xlsx([
            ['certificate_number', 'full_name', 'email', 'completion_date', 'issue_date', 'expiry_date'],
            ['HSE/2023/0042', 'Jane Doe', 'jane@example.com', '', '2023-05-01', ''],
            ['HSE/2023/0043', 'Jane Doe', 'jane@example.com', '', '2023-05-01', ''],
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/admin/templates/{$this->template->uuid}/import",
            ['file' => $file, 'mode' => 'existing']
        );

        $response->assertOk()->assertJsonPath('created', 1);
        $this->assertStringContainsString('already exists', $response->json('failures.0.errors.0'));
        $this->assertEquals(2, Certificate::count());
    }

    public function test_missing_required_columns_rejected(): void
    {
        $file = $this->xlsx([
            ['full_name', 'email', 'issue_date'], // no certificate_number
            ['Jane Doe', 'jane@example.com', '2023-05-01'],
        ]);

        $this->actingAs($this->admin)->postJson(
            "/api/admin/templates/{$this->template->uuid}/import",
            ['file' => $file, 'mode' => 'existing']
        )->assertUnprocessable()
            ->assertJsonFragment(['missing_columns' => ['certificate_number']]);
    }

    public function test_imports_without_template_using_certificate_title(): void
    {
        $file = $this->xlsx([
            ['certificate_number', 'full_name', 'email', 'completion_date', 'issue_date', 'expiry_date', 'certificate_title'],
            ['OLD/001', 'Jane Doe', 'jane@example.com', '', '2022-03-01', '2099-03-01', 'First Aid Level 2'],
        ]);

        $this->actingAs($this->admin)->postJson('/api/admin/certificates/import-existing', ['file' => $file])
            ->assertOk()
            ->assertJsonPath('created', 1);

        $certificate = Certificate::firstWhere('certificate_number', 'OLD/001');
        $this->assertNull($certificate->certificate_template_id);
        $this->assertEquals('First Aid Level 2', $certificate->title);
        $this->assertEquals('sent', $certificate->status->value);

        // Verifies publicly with the title as the credential name.
        $this->getJson('/api/verify?number='.urlencode('OLD/001'))
            ->assertOk()
            ->assertJsonPath('result', 'valid')
            ->assertJsonPath('certificate.template', 'First Aid Level 2');
    }

    public function test_templateless_format_download(): void
    {
        $this->actingAs($this->admin)
            ->get('/api/admin/certificates/import-existing-format')
            ->assertOk()
            ->assertDownload('offline-existing-certificates-format.xlsx');
    }

    public function test_zip_attaches_files_by_certificate_number(): void
    {
        Storage::fake('local');

        $a = Certificate::factory()->sent()->create([
            'certificate_template_id' => $this->template->id,
            'certificate_number' => 'HSE/2023/0042',
        ]);
        Certificate::factory()->sent()->create([
            'certificate_template_id' => $this->template->id,
            'certificate_number' => 'HSE-2023-0043',
        ]);

        $zipPath = tempnam(sys_get_temp_dir(), 'zip').'.zip';
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('hse-2023-0042.pdf', '%PDF-fake-a');
        $zip->addFromString('scans/HSE_2023_0043.PDF', '%PDF-fake-b');
        $zip->addFromString('unrelated.pdf', '%PDF-orphan');
        $zip->close();

        $upload = new UploadedFile($zipPath, 'scans.zip', 'application/zip', null, true);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/certificates/attach-zip', ['file' => $upload]);

        $response->assertOk();
        $this->assertCount(2, $response->json('attached'));
        $this->assertEquals(['unrelated.pdf'], $response->json('unmatched'));

        $a->refresh();
        $this->assertNotNull($a->uploaded_file_path);
        Storage::disk('local')->assertExists($a->uploaded_file_path);
    }

    public function test_expiry_falls_back_to_template_duration(): void
    {
        $file = $this->xlsx([
            ['certificate_number', 'full_name', 'email', 'completion_date', 'issue_date', 'expiry_date'],
            ['HSE/2026/0001', 'Jane Doe', 'jane@example.com', '', '2026-01-01', ''],
        ]);

        $this->actingAs($this->admin)->postJson(
            "/api/admin/templates/{$this->template->uuid}/import",
            ['file' => $file, 'mode' => 'existing']
        )->assertOk();

        $this->assertEquals(
            '2027-01-01',
            Certificate::firstWhere('certificate_number', 'HSE/2026/0001')->expiry_date->format('Y-m-d')
        );
    }
}

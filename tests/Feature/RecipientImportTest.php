<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Group;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class RecipientImportTest extends TestCase
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
            'code' => 'FST',
            'duration' => 1,
            'duration_type' => 'year',
        ]);
        $this->template->blocks()->create([
            'name' => 'Course Title', 'slug' => 'course_title', 'type' => 'text',
            'value' => '{{course_title}}', 'is_dynamic' => true,
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
        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_format_download_includes_dynamic_columns(): void
    {
        $response = $this->actingAs($this->admin)
            ->get("/api/admin/templates/{$this->template->uuid}/import-format");

        $response->assertOk();
        $response->assertDownload('fst-import-format.xlsx');
    }

    public function test_import_rejects_missing_columns(): void
    {
        $file = $this->xlsx([
            ['full_name', 'email'], // missing completion_date, issue_date, course_title
            ['Jane Doe', 'jane@example.com'],
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/templates/{$this->template->uuid}/import", ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonFragment(['missing_columns' => ['completion_date', 'issue_date', 'course_title']]);
    }

    public function test_import_creates_recipients_and_pending_certificates(): void
    {
        $group = Group::factory()->create();

        $file = $this->xlsx([
            ['full_name', 'email', 'completion_date', 'issue_date', 'course_title'],
            ['Jane Doe', 'jane@example.com', '2026-05-01', '2026-06-01', 'Fire Safety'],
            ['John Smith', 'john@example.com', '2026-05-02', '', 'First Aid'],
            ['Bad Row', 'not-an-email', '2026-05-03', '', 'X'],
        ]);

        $response = $this->actingAs($this->admin)->postJson(
            "/api/admin/templates/{$this->template->uuid}/import",
            ['file' => $file, 'group_uuid' => $group->uuid]
        );

        $response->assertOk()->assertJsonPath('created', 2);
        $this->assertCount(1, $response->json('failures'));

        $this->assertEquals(2, Recipient::count());
        $this->assertEquals(2, Certificate::count());

        $cert = Certificate::whereHas('recipient', fn ($q) => $q->where('email', 'jane@example.com'))->first();
        $this->assertEquals('pending', $cert->status->value);
        $this->assertMatchesRegularExpression('/^FST-[A-Z2-9]{8}$/', $cert->certificate_number);
        $this->assertEquals('Fire Safety', $cert->data['course_title']);
        $this->assertEquals('2027-06-01', $cert->expiry_date->format('Y-m-d'));
        $this->assertTrue($group->recipients()->where('email', 'jane@example.com')->exists());
    }

    public function test_reimport_reuses_existing_recipient_by_email(): void
    {
        Recipient::factory()->create(['email' => 'jane@example.com', 'full_name' => 'Old Name']);

        $file = $this->xlsx([
            ['full_name', 'email', 'completion_date', 'issue_date', 'course_title'],
            ['Jane Doe', 'jane@example.com', '2026-05-01', '2026-06-01', 'Fire Safety'],
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/admin/templates/{$this->template->uuid}/import", ['file' => $file])
            ->assertOk();

        $this->assertEquals(1, Recipient::count());
        $this->assertEquals('Jane Doe', Recipient::first()->full_name);
    }
}

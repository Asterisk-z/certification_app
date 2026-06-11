<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Group;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class RecipientBulkAddTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    public function test_bulk_add_from_pasted_text_with_group(): void
    {
        $group = Group::factory()->create();

        $response = $this->actingAs($this->admin)->postJson('/api/admin/recipients/bulk', [
            'text' => "Jane Doe, jane@example.com, +123456\nJohn Smith; john@example.com\nbroken line without email",
            'group_uuids' => [$group->uuid],
        ]);

        $response->assertOk()
            ->assertJsonPath('created', 2)
            ->assertJsonPath('updated', 0);
        $this->assertCount(1, $response->json('failures'));

        $jane = Recipient::where('email', 'jane@example.com')->first();
        $this->assertEquals('Jane Doe', $jane->full_name);
        $this->assertEquals('+123456', $jane->phone);
        $this->assertEquals(2, $group->recipients()->count());
    }

    public function test_bulk_add_accepts_email_first_ordering_and_updates_existing(): void
    {
        Recipient::factory()->create(['email' => 'jane@example.com', 'full_name' => 'Old Name']);

        $this->actingAs($this->admin)->postJson('/api/admin/recipients/bulk', [
            'text' => 'jane@example.com, Jane Doe',
        ])->assertOk()->assertJsonPath('updated', 1)->assertJsonPath('created', 0);

        $this->assertEquals('Jane Doe', Recipient::where('email', 'jane@example.com')->value('full_name'));
        $this->assertEquals(1, Recipient::count());
    }

    public function test_bulk_add_from_excel_file(): void
    {
        $group = Group::factory()->create();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [
            ['full_name', 'email', 'phone'],
            ['Jane Doe', 'jane@example.com', '0700111222'],
            ['John Smith', 'john@example.com', ''],
            ['Bad Row', 'not-an-email', ''],
        ];
        foreach ($rows as $r => $cells) {
            foreach (array_values($cells) as $c => $value) {
                $sheet->setCellValue([$c + 1, $r + 1], $value);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'bulk').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $file = new UploadedFile($path, 'bulk.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->admin)->postJson('/api/admin/recipients/bulk', [
            'file' => $file,
            'group_uuids' => [$group->uuid],
        ]);

        $response->assertOk()->assertJsonPath('created', 2);
        $this->assertCount(1, $response->json('failures'));
        $this->assertEquals(2, $group->recipients()->count());
    }

    public function test_bulk_add_file_with_missing_columns_is_rejected(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setCellValue([1, 1], 'name_only');
        $path = tempnam(sys_get_temp_dir(), 'bulk').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $file = new UploadedFile($path, 'bulk.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $this->actingAs($this->admin)->postJson('/api/admin/recipients/bulk', ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonFragment(['missing_columns' => ['full_name', 'email']]);
    }

    public function test_bulk_format_download(): void
    {
        $this->actingAs($this->admin)
            ->get('/api/admin/recipients/bulk-format')
            ->assertOk()
            ->assertDownload('recipients-bulk-format.xlsx');
    }

    public function test_requires_text_or_file(): void
    {
        $this->actingAs($this->admin)->postJson('/api/admin/recipients/bulk', [])
            ->assertUnprocessable();
    }
}

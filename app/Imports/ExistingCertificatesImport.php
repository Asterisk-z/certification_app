<?php

namespace App\Imports;

use App\Enums\CertificateStatus;
use App\Exports\ExistingCertificatesFormatExport;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Group;
use App\Models\Recipient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Registers certificates that were already issued offline: the original
 * certificate number is kept, the certificate is stored as `sent` (so it
 * verifies as valid and shows in the recipient's portal) and no email is
 * triggered.
 */
class ExistingCertificatesImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    /** @var array<array{row: int, errors: array<string>}> */
    public array $failures = [];

    public function __construct(
        private readonly ?CertificateTemplate $template,
        private readonly ?Group $group,
    ) {}

    public function collection(Collection $rows): void
    {
        $customSlugs = array_values(array_diff(
            ExistingCertificatesFormatExport::headingsFor($this->template),
            ['certificate_number', 'full_name', 'email', 'completion_date', 'issue_date', 'expiry_date', 'certificate_title']
        ));

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // account for heading row
            $data = $row->toArray();

            $validator = Validator::make($data, [
                'certificate_number' => ['required'],
                'full_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'issue_date' => ['required'],
                'completion_date' => ['nullable'],
                'expiry_date' => ['nullable'],
            ]);

            if ($validator->fails()) {
                $this->failures[] = ['row' => $rowNumber, 'errors' => $validator->errors()->all()];

                continue;
            }

            $number = trim((string) $data['certificate_number']);

            if (Certificate::withTrashed()->where('certificate_number', $number)->exists()) {
                $this->failures[] = ['row' => $rowNumber, 'errors' => ["Certificate {$number} already exists — skipped."]];

                continue;
            }

            try {
                $issueDate = $this->parseDate($data['issue_date']);
                $completionDate = filled($data['completion_date'] ?? null) ? $this->parseDate($data['completion_date']) : null;
                $expiryDate = filled($data['expiry_date'] ?? null) ? $this->parseDate($data['expiry_date']) : null;
            } catch (\Throwable) {
                $this->failures[] = ['row' => $rowNumber, 'errors' => ['Invalid completion, issue or expiry date.']];

                continue;
            }

            $recipient = Recipient::withTrashed()->firstOrNew(['email' => strtolower(trim($data['email']))]);
            if ($recipient->trashed()) {
                $recipient->restore();
            }
            $recipient->full_name = trim($data['full_name']);
            $recipient->save();

            if ($this->group) {
                $this->group->recipients()->syncWithoutDetaching([$recipient->id]);
            }

            $custom = [];
            foreach ($customSlugs as $slug) {
                $custom[$slug] = isset($data[$slug]) ? (string) $data[$slug] : '';
            }

            if (! $expiryDate && $this->template?->duration && $this->template?->duration_type) {
                $expiryDate = $this->template->duration_type->addTo($issueDate, $this->template->duration);
            }

            Certificate::create([
                'certificate_template_id' => $this->template?->id,
                'title' => filled($data['certificate_title'] ?? null) ? trim((string) $data['certificate_title']) : null,
                'recipient_id' => $recipient->id,
                'group_id' => $this->group?->id,
                'certificate_number' => $number,
                'data' => $custom,
                'completion_date' => $completionDate,
                'issue_date' => $issueDate,
                'expiry_date' => $expiryDate,
                'status' => $expiryDate && $expiryDate->isPast()
                    ? CertificateStatus::Expired
                    : CertificateStatus::Sent,
                'is_manual' => true,
            ]);

            $this->created++;
        }
    }

    private function parseDate(mixed $value): Carbon
    {
        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
        }

        return Carbon::parse((string) $value);
    }
}

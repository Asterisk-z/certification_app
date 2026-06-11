<?php

namespace App\Imports;

use App\Enums\CertificateStatus;
use App\Exports\ImportFormatExport;
use App\Models\CertificateTemplate;
use App\Models\Group;
use App\Models\Recipient;
use App\Services\CertificateNumberService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class RecipientsImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    /** @var array<array{row: int, errors: array<string>}> */
    public array $failures = [];

    public function __construct(
        private readonly CertificateTemplate $template,
        private readonly ?Group $group,
        private readonly CertificateNumberService $numbers,
    ) {}

    public function collection(Collection $rows): void
    {
        $customSlugs = array_values(array_diff(
            ImportFormatExport::headingsFor($this->template),
            ['full_name', 'email', 'completion_date', 'issue_date']
        ));

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // account for heading row
            $data = $row->toArray();

            $validator = Validator::make($data, [
                'full_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'completion_date' => ['required'],
                'issue_date' => ['nullable'],
            ]);

            if ($validator->fails()) {
                $this->failures[] = ['row' => $rowNumber, 'errors' => $validator->errors()->all()];

                continue;
            }

            try {
                $completionDate = $this->parseDate($data['completion_date']);
                $issueDate = filled($data['issue_date'] ?? null) ? $this->parseDate($data['issue_date']) : now();
            } catch (\Throwable) {
                $this->failures[] = ['row' => $rowNumber, 'errors' => ['Invalid completion or issue date.']];

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

            $expiry = null;
            if ($this->template->duration && $this->template->duration_type) {
                $expiry = $this->template->duration_type->addTo($issueDate, $this->template->duration);
            }

            $this->template->certificates()->create([
                'recipient_id' => $recipient->id,
                'group_id' => $this->group?->id,
                'certificate_number' => $this->numbers->next($this->template),
                'data' => $custom,
                'completion_date' => $completionDate,
                'issue_date' => $issueDate,
                'expiry_date' => $expiry,
                'status' => CertificateStatus::Pending,
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

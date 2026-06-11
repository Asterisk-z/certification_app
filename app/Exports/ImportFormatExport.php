<?php

namespace App\Exports;

use App\Models\CertificateTemplate;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportFormatExport implements FromArray, WithHeadings, WithStyles
{
    public function __construct(private readonly CertificateTemplate $template) {}

    /**
     * Default columns first, then the template's custom dynamic fields.
     *
     * @return array<string>
     */
    public static function headingsFor(CertificateTemplate $template): array
    {
        $defaults = ['full_name', 'email', 'completion_date', 'issue_date'];

        $custom = $template->blocks()
            ->where('is_dynamic', true)
            ->whereNotIn('slug', CertificateTemplate::RESERVED_SLUGS)
            ->pluck('slug')
            ->all();

        return array_merge($defaults, $custom);
    }

    public function headings(): array
    {
        return self::headingsFor($this->template);
    }

    public function array(): array
    {
        $sample = [
            'full_name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'completion_date' => now()->format('Y-m-d'),
            'issue_date' => now()->format('Y-m-d'),
        ];

        $row = [];
        foreach ($this->headings() as $heading) {
            $row[] = $sample[$heading] ?? 'Sample '.str_replace('_', ' ', $heading);
        }

        return [$row];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

<?php

namespace App\Exports;

use App\Models\CertificateTemplate;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExistingCertificatesFormatExport implements FromArray, WithHeadings, WithStyles
{
    public function __construct(private readonly ?CertificateTemplate $template) {}

    /**
     * Columns for registering certificates that were already issued offline:
     * the original number is kept, and the expiry can be stated explicitly.
     *
     * @return array<string>
     */
    public static function headingsFor(?CertificateTemplate $template): array
    {
        $defaults = ['certificate_number', 'full_name', 'email', 'completion_date', 'issue_date', 'expiry_date'];

        if (! $template) {
            // No template: the certificate carries its own credential title.
            return array_merge($defaults, ['certificate_title']);
        }

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
            'certificate_number' => 'LEGACY-2024-001',
            'full_name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'completion_date' => now()->subYear()->format('Y-m-d'),
            'issue_date' => now()->subYear()->format('Y-m-d'),
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'certificate_title' => 'Fire Safety Training',
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

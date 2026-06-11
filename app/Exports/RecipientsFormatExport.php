<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecipientsFormatExport implements FromArray, WithHeadings, WithStyles
{
    public function headings(): array
    {
        return ['full_name', 'email', 'phone'];
    }

    public function array(): array
    {
        return [
            ['Jane Doe', 'jane.doe@example.com', '+44 7700 900000'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

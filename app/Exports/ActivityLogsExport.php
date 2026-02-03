<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ActivityLogsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    protected $activities;

    public function __construct($activities)
    {
        $this->activities = $activities;
    }

    public function collection()
    {
        return $this->activities;
    }

    public function headings(): array
    {
        return [
            'الرقم',
            'المستخدم',
            'الإجراء',
            'الوصف',
            'النوع',
            'التاريخ',
            'IP Address',
        ];
    }

    public function map($activity): array
    {
        return [
            $activity->id,
            $activity->user?->name ?? 'النظام',
            $activity->action,
            $activity->description,
            $activity->subject_type ? class_basename($activity->subject_type) : '-',
            $activity->created_at->format('Y-m-d H:i:s'),
            $activity->ip_address ?? '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

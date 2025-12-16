<?php

namespace Meita\ReportsGenerator\Services\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DataArrayExport implements FromArray, WithHeadings, WithStyles, WithEvents, ShouldAutoSize
{
    public function __construct(private array $rows, private array $options = [])
    {
    }

    public function array(): array
    {
        return array_map(fn ($row) => (array) $row, $this->rows);
    }

    public function headings(): array
    {
        if (isset($this->options['headings'])) {
            return $this->options['headings'];
        }

        if (empty($this->rows)) {
            return [];
        }

        return array_keys((array) $this->rows[0]);
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];

        $headingStyle = $this->options['heading_style'] ?? [
            'font' => ['bold' => true],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'color' => ['rgb' => 'DDDDDD'],
            ],
        ];

        if (!empty($headingStyle)) {
            $styles[1] = $headingStyle;
        }

        if (!empty($this->options['row_styles']) && is_array($this->options['row_styles'])) {
            foreach ($this->options['row_styles'] as $rowNumber => $style) {
                $styles[(int) $rowNumber] = $style;
            }
        }

        return $styles;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;

                // Merge cells if requested
                if (!empty($this->options['merge_cells']) && is_array($this->options['merge_cells'])) {
                    foreach ($this->options['merge_cells'] as $range) {
                        $sheet->getDelegate()->mergeCells($range);
                    }
                }

                // Auto-size is handled by ShouldAutoSize interface. Additional column widths can be set here.
                if (!empty($this->options['column_widths']) && is_array($this->options['column_widths'])) {
                    foreach ($this->options['column_widths'] as $column => $width) {
                        $sheet->getDelegate()->getColumnDimension($column)->setWidth($width);
                    }
                }
            },
        ];
    }
}

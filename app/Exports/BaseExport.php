<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

abstract class BaseExport implements WithStyles, WithEvents
{
    protected string $startDate;
    protected string $endDate;

    // Brand colors
    protected string $brandColor = 'F59E0B';        // Amber-500
    protected string $brandColorDark = 'D97706';     // Amber-600
    protected string $headerTextColor = 'FFFFFF';
    protected string $altRowColor = 'FEF3C7';        // Amber-50
    protected string $borderColor = 'E5E7EB';        // Gray-200
    protected string $titleBgColor = '1F2937';        // Gray-800
    protected string $subtitleColor = '6B7280';       // Gray-500

    public function __construct(string $startDate, string $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    abstract protected function getTitle(): string;
    abstract protected function getHeaderRow(): array;
    abstract protected function getDataRows(): array;
    abstract protected function getCurrencyColumns(): array;
    abstract protected function getColumnWidths(): array;

    /**
     * Get all rows including title rows, header, and data.
     */
    public function buildSheet(): array
    {
        $rows = [];

        // Row 1: Store name
        // Query langsung (bukan StoreSetting::current()) karena export jalan di queue worker
        // yang hidup lama, supaya perubahan nama toko langsung terbaca.
        $rows[] = [strtoupper(\App\Models\StoreSetting::query()->value('store_name') ?: \App\Models\StoreSetting::DEFAULT_NAME)];
        // Row 2: Report title
        $rows[] = [$this->getTitle()];
        // Row 3: Period
        $rows[] = ['Periode: ' . date('d/m/Y', strtotime($this->startDate)) . ' - ' . date('d/m/Y', strtotime($this->endDate))];
        // Row 4: Generated info
        $rows[] = ['Dicetak: ' . now()->format('d/m/Y H:i:s') . ' oleh ' . (auth()->user()->name ?? 'System')];
        // Row 5: Header
        $rows[] = $this->getHeaderRow();
        // Row 6+: Data
        foreach ($this->getDataRows() as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $dataRows = $this->getDataRows();
                $headerRow = $this->getHeaderRow();
                $totalDataRows = count($dataRows);
                $lastRow = 5 + $totalDataRows; // Header is at row 5
                $lastCol = $this->getColumnLetter(count($headerRow));

                // === TITLE AREA (Rows 1-4) ===
                // Merge cells for title rows
                for ($i = 1; $i <= 4; $i++) {
                    $sheet->mergeCells("A{$i}:{$lastCol}{$i}");
                }

                // Row 1: Store name - professional dark text
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 16,
                        'color' => ['rgb' => '111827'], // Gray-900
                        'name' => 'Calibri',
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(24);

                // Row 2: Report title
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 12,
                        'color' => ['rgb' => '374151'], // Gray-700
                        'name' => 'Calibri',
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(2)->setRowHeight(20);

                // Row 3: Period
                $sheet->getStyle('A3')->applyFromArray([
                    'font' => [
                        'size' => 10,
                        'color' => ['rgb' => '6B7280'], // Gray-500
                        'name' => 'Calibri',
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(3)->setRowHeight(18);

                // Row 4: Generated info
                $sheet->getStyle('A4')->applyFromArray([
                    'font' => [
                        'size' => 10,
                        'italic' => true,
                        'color' => ['rgb' => '9CA3AF'], // Gray-400
                        'name' => 'Calibri',
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(4)->setRowHeight(18);

                // === HEADER ROW (Row 5) ===
                $sheet->getStyle("A5:{$lastCol}5")->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 10,
                        'color' => ['rgb' => 'FFFFFF'],
                        'name' => 'Calibri',
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '0F172A'], // Slate-900 for a very professional header
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'outline' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '0F172A'],
                        ],
                    ],
                ]);
                $sheet->getRowDimension(5)->setRowHeight(25);

                // === DATA ROWS (Row 6+) ===
                if ($totalDataRows > 0) {
                    // Base style for all data rows
                    $sheet->getStyle("A6:{$lastCol}{$lastRow}")->applyFromArray([
                        'font' => [
                            'size' => 10,
                            'name' => 'Calibri',
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color' => ['rgb' => 'E5E7EB'], // Gray-200
                            ],
                        ],
                        'alignment' => [
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);

                    // Alternating row colors
                    for ($row = 6; $row <= $lastRow; $row++) {
                        if (($row - 6) % 2 === 1) {
                            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                                'fill' => [
                                    'fillType' => Fill::FILL_SOLID,
                                    'startColor' => ['rgb' => 'F9FAFB'], // Gray-50
                                ],
                            ]);
                        }
                    }

                    // Currency formatting for specified columns
                    foreach ($this->getCurrencyColumns() as $colLetter) {
                        $sheet->getStyle("{$colLetter}6:{$colLetter}{$lastRow}")
                            ->getNumberFormat()
                            ->setFormatCode('#,##0');
                    }

                    // Number column (column A - No.) center alignment
                    $sheet->getStyle("A6:A{$lastRow}")->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);
                }

                // === COLUMN WIDTHS ===
                foreach ($this->getColumnWidths() as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                // === PRINT SETTINGS ===
                $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
            },
        ];
    }

    /**
     * Convert column number (1-based) to Excel column letter.
     */
    protected function getColumnLetter(int $colNumber): string
    {
        $letter = '';
        while ($colNumber > 0) {
            $colNumber--;
            $letter = chr(65 + ($colNumber % 26)) . $letter;
            $colNumber = intval($colNumber / 26);
        }
        return $letter;
    }

    /**
     * Format a number as Indonesian Rupiah string for display.
     */
    protected function formatCurrency($amount): string
    {
        return number_format((float)$amount, 0, ',', '.');
    }
}

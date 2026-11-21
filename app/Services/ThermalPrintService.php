<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\Outbound;
use App\Models\StoreSetting;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;

class ThermalPrintService
{
    /**
     * Max characters per line for 58mm thermal printer (32 chars).
     */
    private const LINE_WIDTH = 32;

    /**
     * ESC/POS constants
     */
    private const ESC = "\x1B";
    private const GS = "\x1D";

    /**
     * Print outbound receipt directly to thermal printer via RAW ESC/POS.
     * Bypasses Windows printer driver entirely.
     */
    public function printOutboundReceipt(Outbound $outbound): void
    {
        $store = StoreSetting::current();
        $printerName = $store->printer_name ?: env('THERMAL_PRINTER_NAME', 'RP58 Printer');

        // Generate ESC/POS data to a temp file
        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nota_' . $outbound->id . '_' . time() . '.bin';

        try {
            $connector = new FilePrintConnector($tempFile);
            $printer = new Printer($connector);

            $this->printHeader($printer, $store);
            $this->printTransactionInfo($printer, $outbound);
            $this->printItems($printer, $outbound);
            $this->printTotals($printer, $outbound);
            $this->printFooter($printer, $store);

            // Feed paper for tear-off (no auto-cutter on Blueprint ECO-58D)
            $printer->feed(4);
            $printer->close();

            // Send the temp file to printer as RAW data (bypass driver)
            $this->sendRawToPrinter($printerName, $tempFile);

        } finally {
            // Clean up temp file
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Send raw binary file to Windows printer using Spooler API.
     * This bypasses the printer driver completely.
     */
    private function sendRawToPrinter(string $printerName, string $filePath): void
    {
        $scriptPath = base_path('scripts' . DIRECTORY_SEPARATOR . 'print-raw.ps1');

        if (!file_exists($scriptPath)) {
            throw new \RuntimeException('Print script not found: ' . $scriptPath);
        }

        $command = sprintf(
            'powershell -ExecutionPolicy Bypass -File "%s" -PrinterName "%s" -FilePath "%s" 2>&1',
            $scriptPath,
            $printerName,
            $filePath
        );

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        $outputStr = implode("\n", $output);

        if ($returnCode !== 0) {
            throw new \RuntimeException('Gagal mengirim ke printer: ' . $outputStr);
        }
    }

    private function printHeader(Printer $printer, StoreSetting $store): void
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text($this->wrap(mb_strtoupper($store->store_name ?: StoreSetting::DEFAULT_NAME)));
        $printer->setEmphasis(false);

        foreach ([
            $store->tagline,
            $store->address,
            $store->phone ? 'Telp: ' . $store->phone : null,
            $store->tax_number ? 'NPWP: ' . $store->tax_number : null,
        ] as $line) {
            if (filled($line)) {
                $printer->text($this->wrap($line));
            }
        }

        $printer->text(str_repeat('-', self::LINE_WIDTH) . "\n");
    }

    private function printTransactionInfo(Printer $printer, Outbound $outbound): void
    {
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $printer->text("No     : " . $outbound->transaction_code . "\n");
        $printer->text("Tgl    : " . $outbound->date->format('d/m/Y') . "\n");

        if ($outbound->orderLabel()) {
            $printer->text("Pesanan: " . $outbound->orderLabel() . "\n");
        }
        if ($outbound->customer_name) {
            $printer->text("Cust   : " . $outbound->customer_name . "\n");
        }
        if ($outbound->destination) {
            $printer->text("Tujuan : " . $outbound->destination . "\n");
        }

        $printer->text("Harga  : " . ucfirst($outbound->price_type_applied) . "\n");
        $printer->text("Kasir  : " . ($outbound->createdBy?->name ?? '-') . "\n");
        $printer->text(str_repeat('-', self::LINE_WIDTH) . "\n");
    }

    private function printItems(Printer $printer, Outbound $outbound): void
    {
        foreach ($outbound->items as $outboundItem) {
            $itemName = $outboundItem->item?->name ?? 'Item Dihapus';

            // Item name (bold)
            $printer->setEmphasis(true);
            $printer->text($this->truncate($itemName, self::LINE_WIDTH) . "\n");
            $printer->setEmphasis(false);

            // Quantity x Price = Subtotal
            $qty = number_format($outboundItem->quantity);
            $unit = $outboundItem->item?->unit?->name ?? 'pcs';
            $unitSell = (float) $outboundItem->sell_price + (float) ($outboundItem->modifiers_total ?? 0);
            $price = number_format($unitSell, 0, ',', '.');
            $subtotal = number_format($outboundItem->subtotal, 0, ',', '.');

            $left = " {$qty}{$unit} x {$price}";
            $printer->text($this->twoColumns($left, $subtotal) . "\n");

            if (! empty($outboundItem->modifiers_snapshot)) {
                $modNames = collect($outboundItem->modifiers_snapshot)->pluck('name')->implode(', ');
                if ($modNames !== '') {
                    $printer->text($this->wrap("  + " . $modNames));
                }
            }

            if (filled($outboundItem->notes)) {
                $printer->text($this->wrap("  * " . $outboundItem->notes));
            }
        }

        $printer->text(str_repeat('=', self::LINE_WIDTH) . "\n");
    }

    /**
     * Cetak Kitchen Order Ticket (KOT) untuk dapur/bar (tanpa harga).
     */
    public function printKitchenTicket(Outbound $outbound, ?iterable $onlyItems = null): void
    {
        $store = StoreSetting::current();
        $printerName = $store->printer_name ?: env('THERMAL_PRINTER_NAME', 'RP58 Printer');
        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kot_' . $outbound->id . '_' . time() . '.bin';

        try {
            $connector = new FilePrintConnector($tempFile);
            $printer = new Printer($connector);

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text("*** TIKET DAPUR (KOT) ***\n");
            $printer->setEmphasis(false);
            $printer->text(str_repeat('-', self::LINE_WIDTH) . "\n");

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("No     : " . $outbound->transaction_code . "\n");
            $printer->text("Jam    : " . now()->format('d/m/Y H:i') . "\n");
            if ($outbound->orderLabel()) {
                $printer->setEmphasis(true);
                $printer->text("Pesanan: " . $outbound->orderLabel() . "\n");
                $printer->setEmphasis(false);
            }
            if ($outbound->customer_name) {
                $printer->text("Cust   : " . $outbound->customer_name . "\n");
            }
            $printer->text(str_repeat('=', self::LINE_WIDTH) . "\n");

            $items = $onlyItems ?? $outbound->items;
            foreach ($items as $line) {
                $itemName = $line->item?->name ?? 'Item';
                $printer->setEmphasis(true);
                $printer->text($this->wrap("{$line->quantity}x {$itemName}"));
                $printer->setEmphasis(false);

                if (! empty($line->modifiers_snapshot)) {
                    foreach ($line->modifiers_snapshot as $mod) {
                        $printer->text($this->wrap("   + " . ($mod['name'] ?? '')));
                    }
                }
                if (filled($line->notes)) {
                    $printer->text($this->wrap("   * Catatan: " . $line->notes));
                }
            }

            $printer->text(str_repeat('-', self::LINE_WIDTH) . "\n");
            $printer->feed(4);
            $printer->close();

            $this->sendRawToPrinter($printerName, $tempFile);
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * Cetak laporan Shift Kasir (X-Report saat masih buka, Z-Report setelah tutup).
     */
    public function printShiftReport(CashierShift $shift, bool $showExpected = true): void
    {
        $store = StoreSetting::current();
        $printerName = $store->printer_name ?: env('THERMAL_PRINTER_NAME', 'RP58 Printer');
        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'shift_' . $shift->id . '_' . time() . '.bin';
        $summary = $shift->summary();

        try {
            $connector = new FilePrintConnector($tempFile);
            $printer = new Printer($connector);

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text($shift->status === CashierShift::STATUS_CLOSED ? "Z-REPORT (TUTUP SHIFT)\n" : "X-REPORT (SHIFT AKTIF)\n");
            $printer->setEmphasis(false);
            $printer->text($this->wrap($store->store_name ?: StoreSetting::DEFAULT_NAME));
            $printer->text(str_repeat('-', self::LINE_WIDTH) . "\n");

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            if (filled($shift->name)) {
                $printer->text("Shift  : " . $shift->name . "\n");
            }
            $printer->text("Kasir  : " . ($shift->members->pluck('name')->implode(', ') ?: ($shift->user?->name ?? '-')) . "\n");
            $printer->text("Buka   : " . $shift->opened_at->format('d/m/Y H:i') . "\n");
            if ($shift->closed_at) {
                $printer->text("Tutup  : " . $shift->closed_at->format('d/m/Y H:i') . "\n");
            }
            $printer->text(str_repeat('=', self::LINE_WIDTH) . "\n");

            $money = fn (float $v) => 'Rp' . number_format($v, 0, ',', '.');
            $printer->text($this->twoColumns('Modal Awal', $money($summary['opening_cash'])) . "\n");
            $printer->text($this->twoColumns('Order Selesai', (string) $summary['orders_count']) . "\n");
            $printer->text($this->twoColumns('Order Void', (string) $summary['void_count']) . "\n");
            $printer->text(str_repeat('-', self::LINE_WIDTH) . "\n");

            foreach ($summary['payments_by_method'] as $method => $row) {
                $label = Outbound::PAYMENT_METHODS[$method] ?? ucfirst($method);
                $printer->text($this->twoColumns($label, $money($row['total'])) . "\n");
            }
            $printer->setEmphasis(true);
            $printer->text($this->twoColumns('Total Omzet', $money($summary['total_sales'])) . "\n");
            $printer->setEmphasis(false);
            $printer->text(str_repeat('-', self::LINE_WIDTH) . "\n");

            $printer->text($this->twoColumns('Kas Masuk', $money($summary['cash_in'])) . "\n");
            $printer->text($this->twoColumns('Kas Keluar', $money($summary['cash_out'])) . "\n");
            if ($showExpected) {
                $printer->setEmphasis(true);
                $printer->text($this->twoColumns('Est. Kas Laci', $money($summary['expected_cash'])) . "\n");
                $printer->setEmphasis(false);
            }

            if ($shift->status === CashierShift::STATUS_CLOSED) {
                $printer->text($this->twoColumns('Kas Aktual', $money((float) $shift->actual_cash)) . "\n");
                $printer->text($this->twoColumns('Selisih', $money((float) $shift->cash_difference)) . "\n");
            }

            $printer->text(str_repeat('-', self::LINE_WIDTH) . "\n");
            $printer->feed(4);
            $printer->close();

            $this->sendRawToPrinter($printerName, $tempFile);
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    private function printTotals(Printer $printer, Outbound $outbound): void
    {
        $printer->setEmphasis(true);

        $itemCount = $outbound->items->count() . " jenis";
        $printer->text($this->twoColumns("Item", $itemCount) . "\n");

        $totalQty = number_format($outbound->items->sum('quantity'));
        $printer->text($this->twoColumns("Qty", $totalQty) . "\n");

        // Pajak & biaya layanan (modul FNB)
        if (! empty($outbound->tax_details)) {
            $printer->setEmphasis(false);
            $printer->text($this->twoColumns("Subtotal", "Rp" . number_format($outbound->total_amount, 0, ',', '.')) . "\n");
            foreach ($outbound->tax_details as $tax) {
                $label = $tax['name'] . ' ' . (float) $tax['rate'] . '%' . ($tax['included'] ? ' (incl)' : '');
                $printer->text($this->twoColumns($label, "Rp" . number_format($tax['amount'], 0, ',', '.')) . "\n");
            }
            $printer->setEmphasis(true);
        }

        $total = "Rp" . number_format($outbound->payableTotal(), 0, ',', '.');
        $printer->text($this->twoColumns("TOTAL", $total) . "\n");

        $printer->setEmphasis(false);

        if ($outbound->payment_method) {
            $method = \App\Models\Outbound::PAYMENT_METHODS[$outbound->payment_method] ?? ucfirst($outbound->payment_method);
            $printer->text($this->twoColumns("Bayar", $method) . "\n");
        }

        if ($outbound->payment_method === 'tunai' && $outbound->paid_amount) {
            $printer->text($this->twoColumns("Tunai", "Rp" . number_format($outbound->paid_amount, 0, ',', '.')) . "\n");
            $printer->text($this->twoColumns("Kembali", "Rp" . number_format($outbound->paid_amount - $outbound->payableTotal(), 0, ',', '.')) . "\n");
        }
        $printer->text(str_repeat('-', self::LINE_WIDTH) . "\n");
    }

    private function printFooter(Printer $printer, StoreSetting $store): void
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        if (filled($store->receipt_footer)) {
            $printer->setEmphasis(true);
            $printer->text($this->wrap($store->receipt_footer));
            $printer->setEmphasis(false);
        }

        $printer->text(now()->format('d/m/Y H:i') . "\n");
    }

    /**
     * Format two-column layout: left-aligned and right-aligned text.
     */
    private function twoColumns(string $left, string $right): string
    {
        $leftLen = mb_strlen($left);
        $rightLen = mb_strlen($right);
        $spaces = self::LINE_WIDTH - $leftLen - $rightLen;

        if ($spaces < 1) {
            $spaces = 1;
        }

        return $left . str_repeat(' ', $spaces) . $right;
    }

    /**
     * Wrap text to the printer line width (keeps long addresses readable).
     */
    private function wrap(string $text): string
    {
        $lines = [];

        foreach (preg_split('/\R/', trim($text)) as $paragraph) {
            $lines[] = wordwrap($paragraph, self::LINE_WIDTH, "\n", true);
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Truncate text to fit line width.
     */
    private function truncate(string $text, int $maxLen): string
    {
        if (mb_strlen($text) > $maxLen) {
            return mb_substr($text, 0, $maxLen);
        }
        return $text;
    }
}

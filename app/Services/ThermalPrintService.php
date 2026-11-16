<?php

namespace App\Services;

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
            $price = number_format($outboundItem->sell_price, 0, ',', '.');
            $subtotal = number_format($outboundItem->subtotal, 0, ',', '.');

            $left = " {$qty}{$unit} x {$price}";
            $printer->text($this->twoColumns($left, $subtotal) . "\n");
        }

        $printer->text(str_repeat('=', self::LINE_WIDTH) . "\n");
    }

    private function printTotals(Printer $printer, Outbound $outbound): void
    {
        $printer->setEmphasis(true);

        $itemCount = $outbound->items->count() . " jenis";
        $printer->text($this->twoColumns("Item", $itemCount) . "\n");

        $totalQty = number_format($outbound->items->sum('quantity'));
        $printer->text($this->twoColumns("Qty", $totalQty) . "\n");

        $total = "Rp" . number_format($outbound->total_amount, 0, ',', '.');
        $printer->text($this->twoColumns("TOTAL", $total) . "\n");

        $printer->setEmphasis(false);

        if ($outbound->payment_method) {
            $method = \App\Models\Outbound::PAYMENT_METHODS[$outbound->payment_method] ?? ucfirst($outbound->payment_method);
            $printer->text($this->twoColumns("Bayar", $method) . "\n");
        }

        if ($outbound->payment_method === 'tunai' && $outbound->paid_amount) {
            $printer->text($this->twoColumns("Tunai", "Rp" . number_format($outbound->paid_amount, 0, ',', '.')) . "\n");
            $printer->text($this->twoColumns("Kembali", "Rp" . number_format($outbound->paid_amount - $outbound->total_amount, 0, ',', '.')) . "\n");
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

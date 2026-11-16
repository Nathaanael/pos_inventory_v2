<?php

namespace App\Jobs;

use App\Exports\InboundReportExport;
use App\Exports\ItemStockExport;
use App\Exports\OutboundReportExport;
use App\Exports\ProfitLossExport;
use App\Exports\StockMovementExport;
use App\Exports\StockOpnameExport;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ExportDataJob implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    public int $timeout = 300; // 5 minutes max

    public function __construct(
        protected string $exportType,
        protected string $startDate,
        protected string $endDate,
        protected int    $userId,
    ) {}

    public function handle(): void
    {
        try {
            $exportClass = $this->resolveExportClass();
            $filename = $this->generateFilename();
            $directory = 'exports';

            // Ensure exports directory exists
            Storage::disk('local')->makeDirectory($directory);

            // Generate the Excel file
            Excel::store($exportClass, "{$directory}/{$filename}", 'local');

            // Send success notification to user
            $user = User::find($this->userId);
            if ($user) {
                Notification::make()
                    ->title('Export Selesai! 🎉')
                    ->body("File {$this->getExportLabel()} berhasil dibuat dan siap didownload.")
                    ->icon('heroicon-o-document-arrow-down')
                    ->iconColor('success')
                    ->actions([
                        Action::make('download')
                            ->label('Download File')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->url(route('export.download', ['filename' => $filename]), shouldOpenInNewTab: true)
                            ->color('success')
                            ->button(),
                    ])
                    ->sendToDatabase($user);
            }

            Log::info("Export completed: {$filename} for user #{$this->userId}");
        } catch (\Throwable $e) {
            Log::error("Export failed: {$e->getMessage()}", [
                'type' => $this->exportType,
                'user_id' => $this->userId,
                'trace' => $e->getTraceAsString(),
            ]);

            // Send failure notification
            $user = User::find($this->userId);
            if ($user) {
                Notification::make()
                    ->title('Export Gagal ❌')
                    ->body("Terjadi kesalahan saat membuat file {$this->getExportLabel()}. Silakan coba lagi.")
                    ->icon('heroicon-o-exclamation-triangle')
                    ->iconColor('danger')
                    ->sendToDatabase($user);
            }

            throw $e;
        }
    }

    protected function resolveExportClass(): object
    {
        return match ($this->exportType) {
            'item_stock' => new ItemStockExport($this->startDate, $this->endDate),
            'inbound' => new InboundReportExport($this->startDate, $this->endDate),
            'outbound' => new OutboundReportExport($this->startDate, $this->endDate),
            'stock_movement' => new StockMovementExport($this->startDate, $this->endDate),
            'profit_loss' => new ProfitLossExport($this->startDate, $this->endDate),
            'stock_opname' => new StockOpnameExport($this->startDate, $this->endDate),
            default => throw new \InvalidArgumentException("Unknown export type: {$this->exportType}"),
        };
    }

    protected function generateFilename(): string
    {
        $labels = [
            'item_stock' => 'Stok_Barang',
            'inbound' => 'Barang_Masuk',
            'outbound' => 'Barang_Keluar',
            'stock_movement' => 'Mutasi_Stok',
            'profit_loss' => 'Laba_Rugi',
            'stock_opname' => 'Stock_Opname',
        ];

        $label = $labels[$this->exportType] ?? 'Export';
        $dateRange = date('Ymd', strtotime($this->startDate)) . '-' . date('Ymd', strtotime($this->endDate));
        $timestamp = now()->format('His');

        return "WJE_{$label}_{$dateRange}_{$timestamp}.xlsx";
    }

    protected function getExportLabel(): string
    {
        $labels = [
            'item_stock' => 'Stok Barang',
            'inbound' => 'Barang Masuk',
            'outbound' => 'Barang Keluar',
            'stock_movement' => 'Mutasi Stok',
            'profit_loss' => 'Laba Rugi',
            'stock_opname' => 'Stock Opname',
        ];

        return $labels[$this->exportType] ?? 'Export';
    }
}

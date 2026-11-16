<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Jobs\ExportDataJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Export Data ke Excel (dulu App\Filament\Pages\ExportData).
 * File dibuat oleh ExportDataJob di storage/app/private/exports dan diunduh lewat route export.download.
 */
class ExportController extends Controller
{
    public const TYPES = [
        'item_stock' => 'Stok Barang — Daftar seluruh barang dengan stok terkini',
        'inbound' => 'Barang Masuk — Transaksi pembelian/penerimaan barang',
        'outbound' => 'Barang Keluar — Transaksi penjualan/pengeluaran barang',
        'stock_movement' => 'Mutasi Stok — Seluruh pergerakan stok masuk & keluar',
        'profit_loss' => 'Laba Rugi — Ringkasan pendapatan, HPP, dan laba',
        'stock_opname' => 'Stock Opname — Hasil stock opname & selisih stok',
    ];

    public function index(Request $request): View
    {
        $perPage = in_array($request->integer('per_page'), [5, 10, 25, 50], true) ? $request->integer('per_page') : 5;
        $files = $this->files();
        $page = max(1, $request->integer('page', 1));

        $paginator = new LengthAwarePaginator(
            array_slice($files, ($page - 1) * $perPage, $perPage),
            count($files),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('pages.app.export-data', [
            'title' => 'Export Data',
            'files' => $paginator,
            'perPage' => $perPage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'export_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'start_date' => ['required', 'date', 'before_or_equal:today'],
            'end_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:start_date'],
        ], [
            'export_type.required' => 'Silakan pilih jenis laporan yang ingin diexport terlebih dahulu.',
            'end_date.after_or_equal' => 'Tanggal mulai tidak boleh lebih besar dari tanggal akhir.',
        ], [
            'export_type' => 'Jenis Laporan',
            'start_date' => 'Tanggal Mulai',
            'end_date' => 'Tanggal Akhir',
        ]);

        try {
            ExportDataJob::dispatch($data['export_type'], $data['start_date'], $data['end_date'], $request->user()->id);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', ['Export Gagal', 'Terjadi kesalahan saat membuat file. Silakan coba lagi.']);
        }

        // Dengan QUEUE_CONNECTION=sync file langsung jadi; dengan antrean, file muncul setelah job selesai.
        return redirect()->route('export-data.index')->with('success', [
            'Export diproses',
            'Anda akan mendapat notifikasi ketika file siap didownload.',
        ]);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $path = 'exports/'.basename($filename);

        if (! Storage::disk('local')->exists($path)) {
            return back()->with('warning', ['File Tidak Ditemukan', 'File yang ingin dihapus tidak ditemukan.']);
        }

        Storage::disk('local')->delete($path);

        return back()->with('success', ['File Dihapus', 'File '.basename($filename).' berhasil dihapus.']);
    }

    /**
     * Semua file export, terbaru dulu.
     *
     * @return array<int, array<string, mixed>>
     */
    private function files(): array
    {
        $disk = Storage::disk('local');

        return collect($disk->files('exports'))
            ->filter(fn ($file) => str_ends_with($file, '.xlsx'))
            ->map(fn ($file) => [
                'name' => basename($file),
                'size' => $this->formatSize($disk->size($file)),
                'timestamp' => $disk->lastModified($file),
                'date' => Carbon::createFromTimestamp($disk->lastModified($file))->setTimezone(config('app.timezone'))->format('d/m/Y H:i:s'),
                'url' => route('export.download', ['filename' => basename($file)]),
            ])
            ->sortByDesc('timestamp')
            ->values()
            ->all();
    }

    private function formatSize(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => round($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => round($bytes / 1024, 1).' KB',
            default => $bytes.' B',
        };
    }
}

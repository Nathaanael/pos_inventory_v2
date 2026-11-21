<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\CashCount;
use App\Models\CashierShift;
use App\Support\CashDenominations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Hitung Kas (modul FNB, admin saja): uang fisik di laci vs uang di sistem per shift kasir.
 *  - Cek dadakan: admin menghitung laci shift yang sedang berjalan.
 *  - Tutup shift: dibuat otomatis oleh ShiftController::close().
 * Form hitung tidak menampilkan angka sistem (blind count); angka & selisih tampil setelah disimpan.
 */
class CashCountController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = (string) $request->query('status');

        $counts = CashCount::query()
            ->with(['shift.user:id,name', 'shift.members:id,name', 'countedBy:id,name'])
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhereHas('shift', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                ->orWhereHas('shift.members', fn ($u) => $u->where('name', 'like', "%{$search}%"))))
            ->when(array_key_exists($status, $this->statusOptions()), fn ($q) => $q->where('status', $status))
            ->latest('counted_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $activeShifts = CashierShift::query()
            ->open()
            ->with(['user:id,name', 'members:id,name'])
            ->withCount('outbounds')
            ->withMax('cashCounts', 'counted_at')
            ->oldest('opened_at')
            ->get();

        return view('pages.inventory.cash-counts.index', [
            'title' => 'Hitung Kas',
            'counts' => $counts,
            'activeShifts' => $activeShifts,
            // Ringkasan X-Report per shift aktif (modal, omzet, tunai/non-tunai, kas masuk/keluar, estimasi laci).
            'summaries' => $activeShifts->mapWithKeys(fn (CashierShift $shift) => [$shift->id => $shift->summary()]),
            'reviewCount' => CashCount::where('status', CashCount::STATUS_REVIEW)->count(),
            'statusOptions' => $this->statusOptions(),
        ]);
    }

    public function create(CashierShift $shift): View|RedirectResponse
    {
        if ($shift->status !== CashierShift::STATUS_OPEN) {
            return redirect()->route('cash-counts.index')->with('error', ['Shift sudah ditutup', 'Hitung kas saat tutup shift sudah tercatat otomatis.']);
        }

        return view('pages.inventory.cash-counts.form', [
            'title' => 'Hitung Kas',
            'shift' => $shift->load(['user:id,name', 'members:id,name']),
        ]);
    }

    public function store(Request $request, CashierShift $shift): RedirectResponse|JsonResponse
    {
        $data = CashDenominations::validate($request);

        $count = DB::transaction(function () use ($shift, $data, $request) {
            $locked = CashierShift::lockForUpdate()->findOrFail($shift->id);
            abort_if($locked->status !== CashierShift::STATUS_OPEN, 409, 'Shift sudah ditutup.');

            return CashCount::record($locked, CashCount::TYPE_SPOT, $data['actual_cash'], $data['denominations'], $request->user()->id, $data['notes']);
        });

        // Hasil (angka sistem & selisih) baru terlihat di halaman detail.
        return $this->respond($request, 'Hitung kas tersimpan', route('cash-counts.show', $count));
    }

    public function show(CashCount $cashCount): View
    {
        return view('pages.inventory.cash-counts.show', [
            'title' => 'Detail Hitung Kas',
            'count' => $cashCount->load(['shift.user:id,name', 'shift.members:id,name', 'countedBy:id,name', 'reviewedBy:id,name']),
        ]);
    }

    /** Setujui selisih di luar toleransi; catatan penjelasan wajib diisi. */
    public function approve(Request $request, CashCount $cashCount): RedirectResponse|JsonResponse
    {
        abort_unless($cashCount->needsReview(), 409, 'Hitung kas ini tidak perlu direview.');

        $data = $request->validate([
            'review_notes' => ['required', 'string', 'max:500'],
        ], [], [
            'review_notes' => 'Penjelasan Selisih',
        ]);

        $cashCount->update([
            'status' => CashCount::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'],
        ]);

        return $this->respond($request, 'Selisih disetujui');
    }

    /** @return array<string, string> */
    private function statusOptions(): array
    {
        return [
            CashCount::STATUS_MATCH => 'Cocok',
            CashCount::STATUS_REVIEW => 'Perlu Review',
            CashCount::STATUS_APPROVED => 'Selisih Disetujui',
        ];
    }
}

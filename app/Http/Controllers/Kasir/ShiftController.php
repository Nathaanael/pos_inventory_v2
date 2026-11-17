<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\CashCount;
use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Models\StoreSetting;
use App\Services\ThermalPrintService;
use App\Support\CashDenominations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Manajemen Shift Kasir: buka shift, catat kas masuk/keluar, tutup shift (blind closing) & cetak X/Z-Report.
 */
class ShiftController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $activeShift = CashierShift::activeFor($user->id);

        $history = CashierShift::query()
            ->with('user:id,name')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('user_id', $user->id))
            ->where('status', CashierShift::STATUS_CLOSED)
            ->latest('closed_at')
            ->paginate(10)
            ->withQueryString();

        return view('pages.kasir.shift', [
            'title' => 'Shift Kasir',
            'activeShift' => $activeShift,
            'summary' => $activeShift?->summary(),
            'movements' => $activeShift ? $activeShift->movements()->with('createdBy:id,name')->get() : collect(),
            'history' => $history,
            // Blind closing: estimasi kas laci shift aktif hanya terlihat oleh admin.
            'showExpected' => $user->isAdmin(),
            // Admin melihat semua shift yang sedang berjalan (semua kasir).
            'otherActiveShifts' => $user->isAdmin()
                ? CashierShift::query()->open()->where('user_id', '!=', $user->id)->with('user:id,name')->withCount('outbounds')->oldest('opened_at')->get()
                : collect(),
        ]);
    }

    public function open(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        if (CashierShift::activeFor($user->id)) {
            return $this->respondError($request, ['Shift masih aktif', 'Tutup shift yang sedang berjalan terlebih dahulu.']);
        }

        $data = $request->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'opening_cash' => 'Modal Awal Kas',
        ]);

        CashierShift::create([
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_cash' => $data['opening_cash'],
            'status' => CashierShift::STATUS_OPEN,
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->respond($request, 'Shift kasir berhasil dibuka.');
    }

    public function storeCashMovement(Request $request): RedirectResponse|JsonResponse
    {
        $shift = CashierShift::activeFor($request->user()->id);
        if (! $shift) {
            return $this->respondError($request, ['Belum ada shift aktif', 'Buka shift terlebih dahulu.']);
        }

        $data = $request->validate([
            'type' => ['required', Rule::in(['in', 'out'])],
            'amount' => ['required', 'numeric', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ], [], [
            'type' => 'Jenis',
            'amount' => 'Nominal',
            'reason' => 'Keterangan',
        ]);

        CashMovement::create([
            'cashier_shift_id' => $shift->id,
            'type' => $data['type'],
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'created_by' => $request->user()->id,
        ]);

        return $this->respond($request, 'Mutasi kas berhasil dicatat.');
    }

    public function close(Request $request): RedirectResponse|JsonResponse
    {
        $shift = CashierShift::activeFor($request->user()->id);
        if (! $shift) {
            return $this->respondError($request, ['Belum ada shift aktif', 'Tidak ada shift yang bisa ditutup.']);
        }

        // Uang fisik: per pecahan atau total langsung (lihat CashDenominations).
        $data = CashDenominations::validate($request);

        DB::transaction(function () use ($shift, $data, $request) {
            $locked = CashierShift::lockForUpdate()->findOrFail($shift->id);
            if ($locked->status !== CashierShift::STATUS_OPEN) {
                return;
            }

            $summary = $locked->summary();
            $expected = (float) $summary['expected_cash'];
            $actual = $data['actual_cash'];

            $locked->update([
                'closed_at' => now(),
                'expected_cash' => $expected,
                'actual_cash' => $actual,
                'cash_difference' => $actual - $expected,
                'status' => CashierShift::STATUS_CLOSED,
                'notes' => $data['notes'] ?? $locked->notes,
            ]);

            // Modul FNB: tutup shift otomatis tercatat di Hitung Kas (selisih di luar toleransi → perlu review admin).
            if (StoreSetting::usesCashCount()) {
                CashCount::record($locked, CashCount::TYPE_CLOSING, $actual, $data['denominations'], $request->user()->id, $data['notes'], $summary);
            }
        });

        return $this->respond($request, 'Shift kasir berhasil ditutup (Z-Report tersimpan).');
    }

    public function printReport(Request $request, CashierShift $shift, ThermalPrintService $printer): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->isAdmin() || (int) $shift->user_id === (int) $request->user()->id, 403);

        try {
            $shift->load('user');
            // Blind closing: kasir tidak melihat estimasi kas laci selama shift masih berjalan.
            $printer->printShiftReport($shift, showExpected: $request->user()->isAdmin() || $shift->status === CashierShift::STATUS_CLOSED);
        } catch (Throwable $e) {
            return $this->respondError($request, ['Gagal cetak laporan shift', $e->getMessage()], 500);
        }

        return $this->respond($request, 'Laporan shift berhasil dicetak!');
    }
}

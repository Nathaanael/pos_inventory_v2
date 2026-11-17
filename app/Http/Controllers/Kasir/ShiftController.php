<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Services\ThermalPrintService;
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
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (CashierShift::activeFor($user->id)) {
            return back()->with('error', ['Shift masih aktif', 'Tutup shift yang sedang berjalan terlebih dahulu.']);
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

        return back()->with('success', 'Shift kasir berhasil dibuka.');
    }

    public function storeCashMovement(Request $request): RedirectResponse
    {
        $shift = CashierShift::activeFor($request->user()->id);
        if (! $shift) {
            return back()->with('error', ['Belum ada shift aktif', 'Buka shift terlebih dahulu.']);
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

        return back()->with('success', 'Mutasi kas berhasil dicatat.');
    }

    public function close(Request $request): RedirectResponse
    {
        $shift = CashierShift::activeFor($request->user()->id);
        if (! $shift) {
            return back()->with('error', ['Belum ada shift aktif', 'Tidak ada shift yang bisa ditutup.']);
        }

        $data = $request->validate([
            'actual_cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], [
            'actual_cash' => 'Uang Fisik di Laci',
        ]);

        DB::transaction(function () use ($shift, $data) {
            $locked = CashierShift::lockForUpdate()->findOrFail($shift->id);
            if ($locked->status !== CashierShift::STATUS_OPEN) {
                return;
            }

            $summary = $locked->summary();
            $expected = (float) $summary['expected_cash'];
            $actual = (float) $data['actual_cash'];

            $locked->update([
                'closed_at' => now(),
                'expected_cash' => $expected,
                'actual_cash' => $actual,
                'cash_difference' => $actual - $expected,
                'status' => CashierShift::STATUS_CLOSED,
                'notes' => $data['notes'] ?? $locked->notes,
            ]);
        });

        return back()->with('success', 'Shift kasir berhasil ditutup (Z-Report tersimpan).');
    }

    public function printReport(Request $request, CashierShift $shift, ThermalPrintService $printer): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() || (int) $shift->user_id === (int) $request->user()->id, 403);

        try {
            $shift->load('user');
            $printer->printShiftReport($shift);
        } catch (Throwable $e) {
            return back()->with('error', ['Gagal cetak laporan shift', $e->getMessage()]);
        }

        return back()->with('success', 'Laporan shift berhasil dicetak!');
    }
}

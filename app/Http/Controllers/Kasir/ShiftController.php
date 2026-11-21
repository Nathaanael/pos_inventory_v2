<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\CashMovement;
use App\Services\ShiftService;
use App\Services\ThermalPrintService;
use App\Support\CashDenominations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $activeShift = CashierShift::activeFor($user->id)?->load(['members:id,name,role', 'openedBy:id,name', 'supervisor:id,name']);
        // Shift bersama yang diikuti tapi belum dibuka penanggung jawab.
        $pendingShift = $activeShift ? null : CashierShift::pendingFor($user->id)?->load(['members:id,name,role', 'supervisor:id,name']);

        $history = CashierShift::query()
            ->with(['user:id,name', 'members:id,name'])
            // Kasir: shift yang pernah ia ikuti; admin: semua shift.
            ->when(! $user->isAdmin(), fn ($q) => $q->whereHas('members', fn ($m) => $m->where('users.id', $user->id)))
            ->where('status', CashierShift::STATUS_CLOSED)
            ->latest('closed_at')
            ->paginate(10)
            ->withQueryString();

        return view('pages.kasir.shift', [
            'title' => 'Shift Kasir',
            'activeShift' => $activeShift,
            'pendingShift' => $pendingShift,
            'summary' => $activeShift?->summary(),
            'movements' => $activeShift ? $activeShift->movements()->with('createdBy:id,name')->get() : collect(),
            'history' => $history,
            // Blind closing: estimasi kas laci shift aktif hanya terlihat oleh admin.
            'showExpected' => $user->isAdmin(),
            // Admin melihat semua shift yang sedang berjalan (semua kasir).
            'otherActiveShifts' => $user->isAdmin()
                ? CashierShift::query()->open()
                    ->whereDoesntHave('members', fn ($m) => $m->where('users.id', $user->id))
                    ->with(['user:id,name', 'members:id,name'])->withCount('outbounds')->oldest('opened_at')->get()
                : collect(),
            // Hanya penanggung jawab shift (atau admin) yang menutup shift.
            'canClose' => $activeShift && ($user->isAdmin() || $activeShift->isSupervisor($user->id)),
        ]);
    }

    public function open(Request $request, ShiftService $shifts): RedirectResponse|JsonResponse
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

        // Tergabung di shift bersama yang belum dibuka: hanya penanggung jawab yang boleh membukanya.
        if ($pending = CashierShift::pendingFor($user->id)) {
            if (! $pending->isSupervisor($user->id)) {
                return $this->respondError($request, ['Menunggu penanggung jawab', 'Shift ini dibuka oleh '.($pending->supervisor?->name ?? 'penanggung jawab').'.'], 403);
            }

            if (! $shifts->open($pending, (float) $data['opening_cash'], $data['notes'] ?? null, $user)) {
                return $this->respondError($request, ['Shift sudah dibuka', 'Shift ini sudah berjalan.']);
            }

            return $this->respond($request, ['Shift dibuka', 'Kasir lain di shift ini sekarang bisa berjualan.']);
        }

        CashierShift::create([
            'user_id' => $user->id,
            'opened_by' => $user->id,
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

    public function close(Request $request, ShiftService $shifts): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $shift = CashierShift::activeFor($user->id);
        if (! $shift) {
            return $this->respondError($request, ['Belum ada shift aktif', 'Tidak ada shift yang bisa ditutup.']);
        }

        // Shift ditutup oleh penanggung jawabnya (atau admin).
        if (! $shift->isSupervisor($user->id) && ! $user->isAdmin()) {
            return $this->respondError($request, ['Hanya penanggung jawab', 'Shift ini ditutup oleh '.($shift->supervisor?->name ?? 'penanggung jawab').'.'], 403);
        }

        // Uang fisik: per pecahan atau total langsung (lihat CashDenominations).
        $shifts->close($shift, CashDenominations::validate($request), $user);

        return $this->respond($request, 'Shift kasir berhasil ditutup (Z-Report tersimpan).');
    }

    public function printReport(Request $request, CashierShift $shift, ThermalPrintService $printer): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $shift->members()->where('users.id', $user->id)->exists(), 403);

        try {
            $shift->load(['user', 'members']);
            // Blind closing: kasir tidak melihat estimasi kas laci selama shift masih berjalan.
            $printer->printShiftReport($shift, showExpected: $request->user()->isAdmin() || $shift->status === CashierShift::STATUS_CLOSED);
        } catch (Throwable $e) {
            return $this->respondError($request, ['Gagal cetak laporan shift', $e->getMessage()], 500);
        }

        return $this->respond($request, 'Laporan shift berhasil dicetak!');
    }
}

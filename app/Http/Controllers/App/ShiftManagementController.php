<?php

namespace App\Http\Controllers\App;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\Outbound;
use App\Models\User;
use App\Services\ShiftService;
use App\Support\CashDenominations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Shift Kasir (back office): admin membuat shift bersama, memilih kasir anggotanya dan satu
 * penanggung jawab. Shift berstatus "Belum dibuka" sampai penanggung jawab membukanya (modal awal)
 * dari panel kasir; penanggung jawab juga yang menutupnya. Satu shift = satu laci kas.
 * Satu kasir hanya boleh tergabung di satu shift yang belum selesai.
 */
class ShiftManagementController extends Controller
{
    public function index(Request $request): View
    {
        // Belum dibuka + sedang berjalan.
        $activeShifts = CashierShift::query()
            ->notClosed()
            ->with(['members:id,name,role', 'openedBy:id,name', 'supervisor:id,name', 'user:id,name'])
            ->withCount('outbounds')
            ->oldest('id')
            ->get();

        $history = CashierShift::query()
            ->where('status', CashierShift::STATUS_CLOSED)
            ->with(['members:id,name', 'user:id,name', 'closedBy:id,name'])
            ->latest('closed_at')
            ->paginate(10)
            ->withQueryString();

        return view('pages.app.shifts.index', [
            'title' => 'Shift Kasir',
            'activeShifts' => $activeShifts,
            'history' => $history,
            'availableUsers' => $this->availableUsers(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'members' => ['required', 'array', 'min:1'],
            'members.*' => ['integer', Rule::exists('users', 'id')->where('is_active', true)],
            'supervisor_id' => ['required', 'integer', Rule::in(array_map('intval', (array) $request->input('members', [])))],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'members.required' => 'Pilih minimal satu kasir.',
            'supervisor_id.required' => 'Pilih penanggung jawab shift.',
            'supervisor_id.in' => 'Penanggung jawab harus salah satu kasir di shift ini.',
        ], [
            'name' => 'Nama Shift',
            'members' => 'Kasir',
            'supervisor_id' => 'Penanggung Jawab',
        ]);

        $this->ensureNotBusy($data['members'], 'members');

        $shift = DB::transaction(function () use ($data, $request) {
            // Belum dibuka: modal awal diisi penanggung jawab saat membuka shift di panel kasir.
            $shift = CashierShift::create([
                'name' => filled($data['name'] ?? null) ? trim($data['name']) : null,
                'user_id' => null, // shift bersama, dikelola admin
                'supervisor_id' => (int) $data['supervisor_id'],
                'opened_at' => null,
                'opening_cash' => 0,
                'status' => CashierShift::STATUS_SCHEDULED,
                'notes' => $data['notes'] ?? null,
            ]);

            $shift->members()->attach(collect($data['members'])->mapWithKeys(fn ($id) => [(int) $id => ['added_by' => $request->user()->id]])->all());

            return $shift;
        });

        return $this->respond($request, ['Shift dibuat', 'Menunggu dibuka oleh '.$shift->supervisor?->name.'.'], route('shifts.show', $shift));
    }

    public function show(CashierShift $shift): View
    {
        $shift->load(['members:id,name,role', 'openedBy:id,name', 'closedBy:id,name', 'supervisor:id,name', 'user:id,name']);
        $memberStats = $this->memberStats($shift);

        return view('pages.app.shifts.show', [
            'title' => 'Detail Shift',
            'shift' => $shift,
            'summary' => $shift->summary(),
            'memberStats' => $memberStats,
            // Pernah bertransaksi di shift ini tapi sudah dikeluarkan.
            'formerMembers' => User::whereIn('id', $memberStats->keys()->diff($shift->members->pluck('id')))->get(['id', 'name']),
            'addedBy' => User::whereIn('id', $shift->members->pluck('pivot.added_by')->filter()->unique())->pluck('name', 'id'),
            'availableUsers' => $shift->isClosed() ? collect() : $this->availableUsers(),
        ]);
    }

    public function addMember(Request $request, CashierShift $shift): RedirectResponse|JsonResponse
    {
        abort_if($shift->isClosed(), 409, 'Shift sudah ditutup.');

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
        ], [], ['user_id' => 'Kasir']);

        $this->ensureNotBusy([$data['user_id']], 'user_id');

        $shift->members()->syncWithoutDetaching([(int) $data['user_id'] => ['added_by' => $request->user()->id]]);

        return $this->respond($request, ['Kasir ditambahkan', User::find($data['user_id'])?->name]);
    }

    public function removeMember(Request $request, CashierShift $shift, User $user): RedirectResponse|JsonResponse
    {
        abort_if($shift->isClosed(), 409, 'Shift sudah ditutup.');

        if ($shift->isSupervisor($user->id)) {
            return $this->respondError($request, ['Tidak bisa dikeluarkan', $user->name.' adalah penanggung jawab. Ganti penanggung jawab terlebih dahulu.'], 422);
        }

        $shift->members()->detach($user->id);

        return $this->respond($request, ['Kasir dikeluarkan dari shift', 'Transaksi yang sudah dibuat '.$user->name.' tetap tercatat di shift ini.']);
    }

    /** Ganti penanggung jawab (harus anggota shift). Mis. bila penanggung jawab berhalangan. */
    public function setSupervisor(Request $request, CashierShift $shift): RedirectResponse|JsonResponse
    {
        abort_if($shift->isClosed(), 409, 'Shift sudah ditutup.');

        $data = $request->validate([
            'supervisor_id' => ['required', 'integer', Rule::in($shift->members()->pluck('users.id')->all())],
        ], [
            'supervisor_id.in' => 'Penanggung jawab harus salah satu kasir di shift ini.',
        ], ['supervisor_id' => 'Penanggung Jawab']);

        $shift->update(['supervisor_id' => (int) $data['supervisor_id']]);

        return $this->respond($request, ['Penanggung jawab diganti', $shift->fresh('supervisor')->supervisor?->name]);
    }

    /** Batalkan shift yang belum dibuka (belum ada transaksi). */
    public function destroy(Request $request, CashierShift $shift): RedirectResponse|JsonResponse
    {
        if (! $shift->isScheduled()) {
            return $this->respondError($request, ['Tidak bisa dibatalkan', 'Hanya shift yang belum dibuka yang bisa dibatalkan.']);
        }

        $shift->delete();

        return $request->expectsJson()
            ? $this->respond($request, 'Shift dibatalkan', route('shifts.index'))
            : redirect()->route('shifts.index')->with('success', 'Shift dibatalkan');
    }

    /** Admin tetap bisa menutup shift berjalan (cadangan bila penanggung jawab berhalangan). */
    public function close(Request $request, CashierShift $shift, ShiftService $shifts): RedirectResponse|JsonResponse
    {
        if (! $shift->isOpen()) {
            return $this->respondError($request, ['Shift sudah ditutup', 'Shift ini tidak bisa ditutup lagi.']);
        }

        $shifts->close($shift, CashDenominations::validate($request), $request->user());

        return $this->respond($request, ['Shift ditutup', 'Z-Report tersimpan.']);
    }

    /**
     * Pengguna aktif yang belum tergabung di shift mana pun yang sedang berjalan.
     *
     * @return Collection<int, User>
     */
    private function availableUsers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereNotIn('id', CashierShift::busyUserIds())
            ->orderByRaw('CASE WHEN role = ? THEN 0 ELSE 1 END', [User::ROLE_KASIR])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    /** @param  array<int, int|string>  $userIds */
    private function ensureNotBusy(array $userIds, string $field): void
    {
        $busy = array_intersect(array_map('intval', $userIds), CashierShift::busyUserIds());
        if ($busy) {
            $names = User::whereIn('id', $busy)->pluck('name')->implode(', ');
            throw ValidationException::withMessages([$field => "{$names} masih tergabung di shift lain yang sedang berjalan."]);
        }
    }

    /**
     * Jumlah transaksi & penjualan per kasir di shift ini (selain transaksi void).
     *
     * @return Collection<int, object{count: int, total: float}>
     */
    private function memberStats(CashierShift $shift): Collection
    {
        return Outbound::query()
            ->where('cashier_shift_id', $shift->id)
            ->where('status', '!=', TransactionStatus::VOIDED->value)
            ->selectRaw('created_by, COUNT(*) as count, SUM(COALESCE(grand_total, total_amount)) as total')
            ->groupBy('created_by')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->created_by => (object) ['count' => (int) $row->count, 'total' => (float) $row->total]]);
    }
}

<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Member: dipakai panel admin (/members) dan panel kasir (/kasir/member).
 * Kasir boleh menambah & mengubah; menghapus hanya admin (rute hapus hanya ada di panel admin).
 */
class MemberController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $phoneSearch = Member::normalizePhone($search);

        $members = Member::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->when($phoneSearch !== '', fn ($q) => $q->orWhere('phone', 'like', "%{$phoneSearch}%"))))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        $isKasir = $request->routeIs('kasir.*');

        return view($isKasir ? 'pages.kasir.members' : 'pages.master.members', [
            'title' => 'Member',
            'members' => $members,
            'routePrefix' => $isKasir ? 'kasir.members' : 'members',
            'canDelete' => ! $isKasir && $request->user()->isAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $member = Member::create([...$this->validated($request), 'created_by' => $request->user()->id]);

        // Dari POS (tambah member cepat): kembalikan data member agar langsung terpilih.
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Member berhasil ditambahkan', 'member' => $this->toOption($member)]);
        }

        return $this->respond($request, 'Member berhasil ditambahkan');
    }

    /**
     * Cari member untuk kolom "Nama pelanggan" di POS (nama atau nomor telepon).
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $phone = Member::normalizePhone($q);

        $members = Member::query()
            ->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->when(preg_match('/^\+?\d{3,}$/', $phone), fn ($query) => $query->orWhere('phone', 'like', "%{$phone}%")))
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ["{$q}%"])
            ->orderBy('name')
            ->limit(8)
            ->get();

        return response()->json($members->map(fn (Member $m) => $this->toOption($m))->values());
    }

    /** @return array{id: int, name: string, phone: string, phone_label: string} */
    private function toOption(Member $member): array
    {
        return [
            'id' => $member->id,
            'name' => $member->name,
            'phone' => $member->phone,
            'phone_label' => $member->formattedPhone(),
        ];
    }

    public function update(Request $request, Member $member): RedirectResponse|JsonResponse
    {
        $member->update($this->validated($request, $member));

        return $this->respond($request, 'Member berhasil diperbarui');
    }

    public function destroy(Request $request, Member $member): RedirectResponse|JsonResponse
    {
        $member->delete();

        return $this->respond($request, 'Member berhasil dihapus');
    }

    /** @return array{name: string, phone: string, birth_date: string} */
    private function validated(Request $request, ?Member $member = null): array
    {
        // Normalisasi dulu agar "0812-3456" dan "08123456" dianggap nomor yang sama.
        $request->merge(['phone' => Member::normalizePhone($request->input('phone'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^\+?[0-9]{8,15}$/', Rule::unique('members', 'phone')->ignore($member)],
            'birth_date' => ['required', 'date', 'before_or_equal:today', 'after:1900-01-01'],
        ], [
            'phone.regex' => 'Nomor telepon harus 8–15 digit angka (boleh diawali +).',
            'phone.unique' => 'Nomor telepon ini sudah terdaftar sebagai member.',
            'birth_date.before_or_equal' => 'Tanggal lahir tidak boleh setelah hari ini.',
        ], [
            'name' => 'Nama Member',
            'phone' => 'Nomor Telepon',
            'birth_date' => 'Tanggal Lahir',
        ]);
    }
}

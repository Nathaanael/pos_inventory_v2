<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    private const IN_USE = 'Supplier ini sedang digunakan dalam transaksi Barang Masuk.';

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $suppliers = Supplier::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.master.suppliers', [
            'title' => 'Supplier',
            'suppliers' => $suppliers,
            'nextCode' => self::generateCode(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        // Kode dibuat ulang di server agar tidak bentrok bila dua orang menambah bersamaan.
        $data['code'] = self::generateCode();

        Supplier::create($data);

        return back()
            ->with('success', 'Berhasil ditambah')
            ->with('create_another', $request->boolean('create_another'));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request, $supplier));

        return back()->with('success', 'Berhasil diperbarui');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->inbounds()->exists()) {
            return back()->with('error', ['Gagal menghapus', self::IN_USE]);
        }

        $supplier->delete();

        return back()->with('success', 'Berhasil dihapus');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $records = Supplier::whereIn('id', (array) $request->input('ids'))->get();

        if ($records->contains(fn ($s) => $s->inbounds()->exists())) {
            return back()->with('error', ['Gagal menghapus', 'Salah satu Supplier yang dipilih sedang digunakan dalam transaksi Barang Masuk.']);
        }

        $records->each->delete();

        return back()->with('success', 'Berhasil dihapus');
    }

    public static function generateCode(): string
    {
        $last = Supplier::query()
            ->where('code', 'like', 'SUP-%')
            ->orderByDesc('code')
            ->first();

        $next = $last ? ((int) str_replace('SUP-', '', $last->code)) + 1 : 1;

        return 'SUP-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'regex:/^[0-9\-\+]+$/', 'min:10', 'max:20'],
            'address' => ['nullable', 'string'],
        ], [], ['name' => 'Nama Supplier', 'phone' => 'Telepon', 'address' => 'Alamat']);
    }
}

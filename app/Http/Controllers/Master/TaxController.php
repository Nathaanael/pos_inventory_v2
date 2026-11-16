<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master Pajak & Biaya Layanan (modul FNB): PB1, service charge, dll.
 * Berlaku ke semua transaksi kasir; barang dengan Pajak (%) sendiri memakai tarif barangnya.
 */
class TaxController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $taxes = Tax::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.master.taxes', [
            'title' => 'Pajak & Biaya Layanan',
            'taxes' => $taxes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Tax::create($this->validated($request));

        return back()
            ->with('success', 'Berhasil ditambah')
            ->with('create_another', $request->boolean('create_another'));
    }

    public function update(Request $request, Tax $tax): RedirectResponse
    {
        $tax->update($this->validated($request));

        return back()->with('success', 'Berhasil diperbarui');
    }

    public function toggle(Tax $tax): RedirectResponse
    {
        $tax->update(['is_active' => ! $tax->is_active]);

        return back()->with('success', 'Berhasil diperbarui');
    }

    public function destroy(Tax $tax): RedirectResponse
    {
        // Transaksi lama menyimpan snapshot pajaknya sendiri, jadi aman dihapus.
        $tax->delete();

        return back()->with('success', 'Berhasil dihapus');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(Tax::TYPES))],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'method' => ['required', Rule::in(array_keys(Tax::METHODS))],
            'applies_to' => ['required', Rule::in(array_keys(Tax::APPLIES_TO))],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], [], [
            'name' => 'Nama',
            'type' => 'Jenis',
            'rate' => 'Persentase',
            'method' => 'Tipe',
            'applies_to' => 'Berlaku untuk',
            'sort_order' => 'Urutan',
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}

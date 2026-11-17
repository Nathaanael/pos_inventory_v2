<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UnitController extends Controller
{
    public const OPTIONS = [
        'pcs' => 'Pcs',
        'cup' => 'Cup',
        'porsi' => 'Porsi',
        'gram' => 'Gram',
        'kg' => 'Kg',
        'ml' => 'Ml',
        'liter' => 'Liter',
        'botol' => 'Botol',
        'pack' => 'Pack',
        'box' => 'Box',
        'meter' => 'Meter',
        'yard' => 'Yard',
    ];

    private const IN_USE = 'Satuan ini sedang digunakan oleh data Master Barang.';

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $units = Unit::withTrashed()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.master.units', [
            'title' => 'Satuan',
            'units' => $units,
            'options' => self::OPTIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Unit::create($this->validated($request));

        return back()
            ->with('success', 'Berhasil ditambah')
            ->with('create_another', $request->boolean('create_another'));
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $unit->update($this->validated($request, $unit));

        return back()->with('success', 'Berhasil diperbarui');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        if ($unit->items()->exists()) {
            return back()->with('error', ['Gagal menghapus', self::IN_USE]);
        }

        $unit->delete();

        return back()->with('success', 'Berhasil dihapus');
    }

    public function restore(Unit $unit): RedirectResponse
    {
        $unit->restore();

        return back()->with('success', 'Berhasil dipulihkan');
    }

    public function forceDelete(Unit $unit): RedirectResponse
    {
        if ($unit->items()->exists()) {
            return back()->with('error', ['Gagal menghapus permanen', self::IN_USE]);
        }

        $unit->forceDelete();

        return back()->with('success', 'Berhasil dihapus permanen');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $records = Unit::whereIn('id', (array) $request->input('ids'))->get();

        if ($records->contains(fn ($u) => $u->items()->exists())) {
            return back()->with('error', ['Gagal menghapus', 'Salah satu Satuan yang dipilih sedang digunakan oleh data Master Barang.']);
        }

        $records->each->delete();

        return back()->with('success', 'Berhasil dihapus');
    }

    private function validated(Request $request, ?Unit $unit = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('units', 'name')->ignore($unit)],
        ], [
            'name.unique' => 'Satuan ini sudah ada.',
        ], ['name' => 'Nama']);
    }
}

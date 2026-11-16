<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master meja (modul FNB). Hanya bisa dibuka bila fitur "Manajemen Meja" aktif.
 */
class DiningTableController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $active = $request->query('is_active');

        $tables = DiningTable::query()
            ->withCount('activeOrders')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('number', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->when(in_array($active, ['1', '0'], true), fn ($q) => $q->where('is_active', $active === '1'))
            ->orderBy('number')
            ->paginate(10)
            ->withQueryString();

        return view('pages.master.dining-tables', [
            'title' => 'Master Meja',
            'tables' => $tables,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        DiningTable::create($this->validated($request));

        return back()
            ->with('success', 'Berhasil ditambah')
            ->with('create_another', $request->boolean('create_another'));
    }

    public function update(Request $request, DiningTable $meja): RedirectResponse
    {
        $meja->update($this->validated($request, $meja));

        return back()->with('success', 'Berhasil diperbarui');
    }

    public function toggle(DiningTable $meja): RedirectResponse
    {
        $meja->update(['is_active' => ! $meja->is_active]);

        return back()->with('success', 'Berhasil diperbarui');
    }

    public function destroy(DiningTable $meja): RedirectResponse
    {
        $meja->delete();

        return back()->with('success', 'Berhasil dihapus');
    }

    private function validated(Request $request, ?DiningTable $table = null): array
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:20', Rule::unique('dining_tables', 'number')->ignore($table)],
            'name' => ['nullable', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ], [
            'number.unique' => 'Nomor meja ini sudah dipakai.',
        ], ['number' => 'Nomor Meja', 'name' => 'Nama Meja', 'capacity' => 'Jumlah Kursi']);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}

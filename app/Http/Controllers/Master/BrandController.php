<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BrandController extends Controller
{
    private const IN_USE = 'Merk ini sedang digunakan oleh data Master Barang.';

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $brands = Brand::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.master.brands', [
            'title' => 'Merk',
            'brands' => $brands,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['logo'] = $request->file('logo')?->store('brands', 'public');

        Brand::create($data);

        return back()
            ->with('success', 'Berhasil ditambah')
            ->with('create_another', $request->boolean('create_another'));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('logo') || $request->boolean('remove_logo')) {
            if ($brand->logo) {
                Storage::disk('public')->delete($brand->logo);
            }
            $data['logo'] = $request->file('logo')?->store('brands', 'public');
        }

        $brand->update($data);

        return back()->with('success', 'Berhasil diperbarui');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->items()->exists()) {
            return back()->with('error', ['Gagal menghapus', self::IN_USE]);
        }

        $brand->delete();

        return back()->with('success', 'Berhasil dihapus');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $records = Brand::whereIn('id', (array) $request->input('ids'))->get();

        if ($records->contains(fn ($b) => $b->items()->exists())) {
            return back()->with('error', ['Gagal menghapus', 'Salah satu Merk yang dipilih sedang digunakan oleh data Master Barang.']);
        }

        $records->each->delete();

        return back()->with('success', 'Berhasil dihapus');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'description' => ['nullable', 'string'],
        ], [], ['name' => 'Nama', 'logo' => 'Logo', 'description' => 'Deskripsi']);

        unset($data['logo']);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}

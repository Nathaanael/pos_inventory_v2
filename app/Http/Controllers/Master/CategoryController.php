<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    private const IN_USE = 'Kategori ini sedang digunakan oleh data Master Barang atau memiliki Sub Kategori.';

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        // Sama seperti versi Filament: data terhapus (soft delete) tetap tampil agar bisa dipulihkan.
        $categories = Category::withTrashed()
            ->with('parent')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('parent', fn ($p) => $p->where('name', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.master.categories', [
            'title' => 'Kategori',
            'categories' => $categories,
            'parentOptions' => Category::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $category = Category::create($this->validated($request));

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'item' => ['id' => $category->id, 'name' => $category->name]]);
        }

        return $this->back($request, 'Berhasil ditambah');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));

        return back()->with('success', 'Berhasil diperbarui');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($this->inUse($category)) {
            return back()->with('error', ['Gagal menghapus', self::IN_USE]);
        }

        $category->delete();

        return back()->with('success', 'Berhasil dihapus');
    }

    public function restore(Category $category): RedirectResponse
    {
        $category->restore();

        return back()->with('success', 'Berhasil dipulihkan');
    }

    public function forceDelete(Category $category): RedirectResponse
    {
        if ($this->inUse($category)) {
            return back()->with('error', ['Gagal menghapus permanen', self::IN_USE]);
        }

        $category->forceDelete();

        return back()->with('success', 'Berhasil dihapus permanen');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $records = Category::whereIn('id', (array) $request->input('ids'))->get();

        if ($records->contains(fn ($c) => $this->inUse($c))) {
            return back()->with('error', ['Gagal menghapus', 'Salah satu Kategori yang dipilih sedang digunakan oleh data Master Barang atau memiliki Sub Kategori.']);
        }

        $records->each->delete();

        return back()->with('success', 'Berhasil dihapus');
    }

    private function inUse(Category $category): bool
    {
        return $category->items()->exists() || $category->children()->exists();
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', Rule::exists('categories', 'id'), Rule::notIn(array_filter([$category?->id]))],
        ], [], ['name' => 'Nama', 'parent_id' => 'Kategori Induk']);
    }

    /** "Simpan & buat lagi" membuka kembali modal tambah. */
    private function back(Request $request, string $message): RedirectResponse
    {
        return back()
            ->with('success', $message)
            ->with('create_another', $request->boolean('create_another'));
    }
}

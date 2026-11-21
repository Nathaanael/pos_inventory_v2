<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\ModifierGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Master Modifier & Topping (modul FNB): grup pilihan (mis. Level Gula, Topping) beserta opsi & tambahan harganya.
 */
class ModifierGroupController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        $groups = ModifierGroup::query()
            ->with(['modifiers' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->withCount('items')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.master.modifiers', [
            'title' => 'Modifier & Topping',
            'groups' => $groups,
        ]);
    }

    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $this->validated($request);

        $group = DB::transaction(function () use ($data) {
            $group = ModifierGroup::create($data['group']);
            foreach ($data['modifiers'] as $index => $mod) {
                $group->modifiers()->create([
                    ...$mod,
                    'sort_order' => $index,
                ]);
            }

            return $group;
        });

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'item' => ['id' => $group->id, 'name' => $group->name]]);
        }

        return back()
            ->with('success', 'Berhasil ditambah')
            ->with('create_another', $request->boolean('create_another'));
    }

    public function update(Request $request, ModifierGroup $modifierGroup): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $modifierGroup) {
            $modifierGroup->update($data['group']);

            $keepIds = collect($data['modifiers'])->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
            $modifierGroup->modifiers()->whereNotIn('id', $keepIds)->delete();

            foreach ($data['modifiers'] as $index => $mod) {
                $existing = isset($mod['id']) ? $modifierGroup->modifiers()->find($mod['id']) : null;
                $payload = [
                    'name' => $mod['name'],
                    'price_delta' => $mod['price_delta'],
                    'is_active' => $mod['is_active'],
                    'sort_order' => $index,
                ];
                $existing ? $existing->update($payload) : $modifierGroup->modifiers()->create($payload);
            }
        });

        return back()->with('success', 'Berhasil diperbarui');
    }

    public function toggle(ModifierGroup $modifierGroup): RedirectResponse
    {
        $modifierGroup->update(['is_active' => ! $modifierGroup->is_active]);

        return back()->with('success', 'Berhasil diperbarui');
    }

    public function destroy(ModifierGroup $modifierGroup): RedirectResponse
    {
        $modifierGroup->delete();

        return back()->with('success', 'Berhasil dihapus');
    }

    /** @return array{group: array<string, mixed>, modifiers: array<int, array<string, mixed>>} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'min_select' => ['nullable', 'integer', 'min:0', 'max:20'],
            'max_select' => ['required', 'integer', 'min:1', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'modifiers' => ['required', 'array', 'min:1'],
            'modifiers.*.id' => ['nullable', 'integer'],
            'modifiers.*.name' => ['required', 'string', 'max:100'],
            'modifiers.*.price_delta' => ['nullable', 'numeric', 'min:0'],
            'modifiers.*.is_active' => ['nullable', 'boolean'],
        ], [
            'modifiers.required' => 'Tambahkan minimal satu opsi pilihan.',
            'modifiers.min' => 'Tambahkan minimal satu opsi pilihan.',
        ], [
            'name' => 'Nama Grup',
            'min_select' => 'Min Pilih',
            'max_select' => 'Maks Pilih',
            'modifiers.*.name' => 'Nama Opsi',
            'modifiers.*.price_delta' => 'Tambahan Harga',
        ]);

        $isRequired = $request->boolean('is_required');
        $minSelect = (int) ($validated['min_select'] ?? ($isRequired ? 1 : 0));
        $maxSelect = max($minSelect, (int) $validated['max_select']);

        return [
            'group' => [
                'name' => $validated['name'],
                'min_select' => $minSelect,
                'max_select' => $maxSelect,
                'is_required' => $isRequired,
                'is_active' => $request->boolean('is_active'),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ],
            'modifiers' => array_map(fn ($row) => [
                'id' => $row['id'] ?? null,
                'name' => $row['name'],
                'price_delta' => (float) ($row['price_delta'] ?? 0),
                'is_active' => (bool) ($row['is_active'] ?? true),
            ], array_values($validated['modifiers'])),
        ];
    }
}

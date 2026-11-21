<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Item;
use App\Models\ModifierGroup;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\StoreSetting;
use App\Models\Supplier;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $brandId = $request->query('brand_id');
        $supplierId = $request->query('supplier');
        $active = $request->query('is_active');
        $recipeStatus = $request->query('recipe_status');
        $hasBrand = StoreSetting::hasFeature(Feature::Brand);
        $usesRecipes = StoreSetting::usesRecipes();

        $with = ['suppliers', 'itemSuppliers', 'latestMovement'];
        if ($usesRecipes) {
            $with[] = 'recipe.ingredients.ingredientItem';
        }

        $items = Item::query()
            ->with($with)
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhereHas('suppliers', fn ($s) => $s->where('name', 'like', "%{$search}%"))))
            ->when($hasBrand && filled($brandId), fn ($q) => $q->where('brand_id', $brandId))
            ->when(filled($supplierId), fn ($q) => $q->whereHas('itemSuppliers', fn ($s) => $s->where('supplier_id', $supplierId)))
            ->when(in_array($active, ['1', '0'], true), fn ($q) => $q->where('is_active', $active === '1'))
            ->when($usesRecipes && $recipeStatus === 'incomplete', fn ($q) => $q
                ->where('item_type', Item::TYPE_MENU)
                ->where('track_stock', false)
                ->where(fn ($sub) => $sub->where('recipe_incomplete', true)->orDoesntHave('recipe')))
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('pages.items.index', [
            'title' => 'Master Barang',
            'items' => $items,
            'stats' => $this->stats(),
            'hasBrand' => $hasBrand,
            'brandOptions' => $hasBrand ? Brand::orderBy('name')->pluck('name', 'id') : collect(),
            'supplierOptions' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'usesSuppliers' => StoreSetting::usesSuppliers(),
            'usesRecipes' => $usesRecipes,
        ]);
    }

    public function create(): View
    {
        return view('pages.items.form', [
            'title' => 'Tambah Master Barang',
            'item' => new Item(['code' => self::generateCode(), 'is_active' => true, 'current_stock' => 0]),
            'suppliers' => [],
            ...$this->formOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($request, $data) {
            $item = Item::create([
                'harga_beli' => 0,
                ...$data['item'],
                'code' => self::generateCode(),
                'current_stock' => 0,
                'image' => $this->hasImageFeature() ? $request->file('image')?->store('items', 'public') : null,
            ]);

            if (StoreSetting::usesModifiers()) {
                $item->modifierGroups()->sync($data['modifier_group_ids']);
            }

            if (StoreSetting::usesRecipes()) {
                $this->syncRecipe($item, $data['recipe']);
            }

            if (StoreSetting::usesRecipes()) {
                $this->syncRecipe($item, $data['recipe']);
            }

            // Modul dengan supplier: harga beli diambil dari supplier pertama.
            // Modul tanpa supplier (FNB): harga beli (HPP) sudah diisi langsung di $data['item'].
            if (StoreSetting::usesSuppliers()) {
                foreach ($data['suppliers'] as $row) {
                    $item->itemSuppliers()->create($row);
                }

                $item->syncAggregateFields();
            }
        });

        if ($request->boolean('create_another')) {
            return redirect()->route('items.create')->with('success', 'Berhasil ditambah');
        }

        return redirect()->route('items.index')->with('success', 'Berhasil ditambah');
    }

    public function edit(Item $item): View
    {
        $item->load(['itemSuppliers', 'modifierGroups', 'recipe.ingredients']);

        return view('pages.items.form', [
            'title' => 'Ubah Master Barang',
            'item' => $item,
            'suppliers' => $item->itemSuppliers->map(fn ($s) => [
                'id' => $s->id,
                'supplier_id' => $s->supplier_id,
                'harga_beli' => (float) $s->harga_beli,
                'supplier_sku' => $s->supplier_sku,
                'current_stock' => (int) $s->current_stock,
            ])->all(),
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $data = $this->validated($request, $item);

        DB::transaction(function () use ($request, $data, $item) {
            $attributes = $data['item'];

            if ($this->hasImageFeature() && ($request->hasFile('image') || $request->boolean('remove_image'))) {
                if ($item->image) {
                    Storage::disk('public')->delete($item->image);
                }
                $attributes['image'] = $request->file('image')?->store('items', 'public');
            }

            $item->update($attributes);

            if (StoreSetting::usesModifiers()) {
                $item->modifierGroups()->sync($data['modifier_group_ids']);
            }

            if (StoreSetting::usesRecipes()) {
                $this->syncRecipe($item, $data['recipe']);
            }

            if (StoreSetting::usesRecipes()) {
                $this->syncRecipe($item, $data['recipe']);
            }

            // Modul tanpa supplier (FNB): data supplier lama (bila ada) dibiarkan apa adanya.
            if (! StoreSetting::usesSuppliers()) {
                return;
            }

            // Sinkronkan baris supplier: hapus yang dibuang lebih dulu (agar supplier yang sama
            // boleh ditambahkan lagi tanpa bentrok unique index), lalu ubah yang ada & tambah yang baru.
            // Stok per supplier tidak diubah dari form (dikelola lewat transaksi).
            $keepIds = collect($data['suppliers'])->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
            $item->itemSuppliers()->whereNotIn('id', $keepIds)->delete();

            foreach ($data['suppliers'] as $row) {
                $existing = isset($row['id']) ? $item->itemSuppliers()->find($row['id']) : null;
                unset($row['id']);

                $existing ? $existing->update($row) : $item->itemSuppliers()->create($row);
            }

            $item->syncAggregateFields();
        });

        return redirect()->route('items.index')->with('success', 'Berhasil diperbarui');
    }

    /** Isi modal "Histori": 10 pergerakan stok terakhir. */
    public function history(Item $item): View
    {
        return view('pages.items.partials.history', [
            'movements' => $item->stockMovements()->latest()->take(10)->get(),
        ]);
    }

    /** Isi panel "Lihat" (slide-over). */
    public function show(Item $item): View
    {
        $item->load(['brand', 'category', 'unit', 'itemSuppliers.supplier']);

        return view('pages.items.partials.detail', ['item' => $item]);
    }

    public static function generateCode(): string
    {
        $last = Item::withTrashed()
            ->where('code', 'like', 'ITM-%')
            ->orderByDesc('code')
            ->first();

        $next = $last ? ((int) str_replace('ITM-', '', $last->code)) + 1 : 1;

        return 'ITM-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    private function stats(): array
    {
        $lowStock = Item::where('is_active', true)
            ->where('track_stock', true)
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->count();

        return [
            'total' => Item::count(),
            'asset' => (float) Item::where('is_active', true)->sum(DB::raw('current_stock * harga_beli')),
            'low_stock' => $lowStock,
            'movements_today' => StockMovement::whereDate('created_at', today())->count(),
        ];
    }

    private function formOptions(): array
    {
        $hasBrand = StoreSetting::hasFeature(Feature::Brand);

        return [
            'hasBrand' => $hasBrand,
            'hasWholesale' => StoreSetting::hasFeature(Feature::WholesalePrice),
            'hasImage' => $this->hasImageFeature(),
            'brandOptions' => $hasBrand ? Brand::orderBy('name')->pluck('name', 'id') : collect(),
            'categoryOptions' => Category::orderBy('name')->pluck('name', 'id'),
            'unitOptions' => Unit::orderBy('name')->pluck('name', 'id'),
            'supplierOptions' => Supplier::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'usesSuppliers' => StoreSetting::usesSuppliers(),
            'usesTaxes' => StoreSetting::usesTaxes(),
            'usesModifiers' => StoreSetting::usesModifiers(),
            'usesRecipes' => StoreSetting::usesRecipes(),
            'modifierGroupOptions' => StoreSetting::usesModifiers() ? ModifierGroup::active()->get(['id', 'name']) : collect(),
            'ingredientOptions' => StoreSetting::usesRecipes()
                ? Item::where('item_type', Item::TYPE_INGREDIENT)->with('unit:id,name')->orderBy('name')->get(['id', 'name', 'unit_id', 'harga_beli'])
                : collect(),
        ];
    }

    private function hasImageFeature(): bool
    {
        return StoreSetting::hasFeature(Feature::ItemImage);
    }

    /**
     * @return array{item: array<string, mixed>, suppliers: array<int, array<string, mixed>>}
     */
    private function validated(Request $request, ?Item $item = null): array
    {
        $hasWholesale = StoreSetting::hasFeature(Feature::WholesalePrice);
        $hasBrand = StoreSetting::hasFeature(Feature::Brand);
        $usesSuppliers = StoreSetting::usesSuppliers();
        $usesTaxes = StoreSetting::usesTaxes();

        // Modul dengan supplier: harga beli per supplier (min. 1). Modul FNB: satu Harga Beli (HPP).
        $supplierRules = $usesSuppliers
            ? [
                'suppliers' => ['required', 'array', 'min:1'],
                'suppliers.*.id' => ['nullable', 'integer'],
                'suppliers.*.supplier_id' => ['required', 'distinct', Rule::exists('suppliers', 'id')],
                'suppliers.*.harga_beli' => ['required', 'numeric', 'min:0'],
                'suppliers.*.supplier_sku' => ['nullable', 'string', 'max:255'],
            ]
            : ['harga_beli' => ['required', 'numeric', 'min:0']];

        // Pajak per barang (opsional): kosong = ikut master Pajak & Biaya Layanan.
        $taxRules = $usesTaxes
            ? [
                'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'tax_method' => ['nullable', 'required_with:tax_rate', Rule::in(['inclusive', 'exclusive'])],
            ]
            : [];

        $validated = $request->validate([
            ...$supplierRules,
            ...$taxRules,
            'name' => ['required', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('items', 'barcode')->ignore($item)],
            'description' => ['nullable', 'string'],
            'harga_jual_retail' => ['required', 'numeric', 'min:0'],
            'harga_jual_grosir' => [Rule::requiredIf($hasWholesale), 'nullable', 'numeric', 'min:0'],
            'min_grosir_qty' => [Rule::requiredIf($hasWholesale), 'nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'unit_id' => ['required', Rule::exists('units', 'id')],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'rack_location' => ['nullable', 'string', 'max:255'],
            'item_type' => ['nullable', Rule::in(array_keys(Item::TYPES))],
            'track_stock' => ['nullable', 'boolean'],
            'recipe_yield_qty' => ['nullable', 'numeric', 'min:0.0001'],
            'recipe_ingredients' => ['nullable', 'array'],
            'recipe_ingredients.*.ingredient_item_id' => ['required_with:recipe_ingredients', 'integer', Rule::exists('items', 'id')],
            'recipe_ingredients.*.quantity' => ['required_with:recipe_ingredients', 'numeric', 'min:0.0001'],
            'recipe_ingredients.*.unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')],
            'modifier_group_ids' => ['nullable', 'array'],
            'modifier_group_ids.*' => ['integer', Rule::exists('modifier_groups', 'id')],
        ], [
            'barcode.unique' => 'barcode ini sudah ada',
            'suppliers.required' => 'Tambahkan minimal satu supplier.',
            'suppliers.min' => 'Tambahkan minimal satu supplier.',
            'suppliers.*.supplier_id.distinct' => 'Supplier tidak boleh dipilih dua kali.',
        ], [
            'name' => 'Nama',
            'barcode' => 'Barcode',
            'harga_jual_retail' => 'Harga Jual Retail',
            'harga_jual_grosir' => 'Harga Jual Grosir',
            'min_grosir_qty' => 'Min Qty Grosir',
            'image' => 'Gambar',
            'brand_id' => 'Merk',
            'category_id' => 'Kategori',
            'unit_id' => 'Satuan',
            'min_stock' => 'Min Stok',
            'rack_location' => 'Lokasi Rak',
            'suppliers.*.supplier_id' => 'Supplier',
            'suppliers.*.harga_beli' => 'Harga Beli',
            'suppliers.*.supplier_sku' => 'SKU Supplier',
            'harga_beli' => 'Harga Beli (HPP)',
            'tax_rate' => 'Persentase Pajak',
            'tax_method' => 'Tipe Pajak',
        ]);

        $attributes = [
            'name' => $validated['name'],
            'barcode' => $validated['barcode'] ?? null,
            'description' => $validated['description'] ?? null,
            'harga_jual_retail' => $validated['harga_jual_retail'],
            'category_id' => $validated['category_id'],
            'unit_id' => $validated['unit_id'],
            'min_stock' => $validated['min_stock'],
            'rack_location' => $validated['rack_location'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];

        // Field milik fitur yang nonaktif tidak disentuh (sama seperti ->visible() di Filament).
        if ($hasWholesale) {
            $attributes['harga_jual_grosir'] = $validated['harga_jual_grosir'];
            $attributes['min_grosir_qty'] = $validated['min_grosir_qty'];
        }
        if ($hasBrand) {
            $attributes['brand_id'] = $validated['brand_id'] ?? null;
        }
        if (! $usesSuppliers) {
            $attributes['harga_beli'] = $validated['harga_beli'];
        }
        if ($usesTaxes) {
            $hasRate = filled($validated['tax_rate'] ?? null);
            $attributes['tax_rate'] = $hasRate ? $validated['tax_rate'] : null;
            $attributes['tax_method'] = $hasRate ? $validated['tax_method'] : null;
        }
        if (StoreSetting::usesRecipes()) {
            $attributes['item_type'] = $validated['item_type'] ?? Item::TYPE_MENU;
            $attributes['track_stock'] = $attributes['item_type'] === Item::TYPE_MENU
                ? $request->boolean('track_stock', false)
                : true;
            $attributes['recipe_incomplete'] = $attributes['item_type'] === Item::TYPE_MENU && ! $attributes['track_stock']
                ? ($request->boolean('recipe_incomplete', false) || empty(array_filter(
                    $validated['recipe_ingredients'] ?? [],
                    fn ($row) => ! empty($row['ingredient_item_id']) && (float) ($row['quantity'] ?? 0) > 0
                )))
                : false;
        }

        $suppliers = array_map(fn ($row) => [
            'id' => $row['id'] ?? null,
            'supplier_id' => $row['supplier_id'],
            'harga_beli' => $row['harga_beli'],
            'supplier_sku' => $row['supplier_sku'] ?? null,
        ], array_values($validated['suppliers'] ?? []));

        $recipeRows = array_values(array_filter(
            $validated['recipe_ingredients'] ?? [],
            fn ($row) => ! empty($row['ingredient_item_id']) && (float) ($row['quantity'] ?? 0) > 0
        ));

        return [
            'item' => $attributes,
            'suppliers' => $suppliers,
            'modifier_group_ids' => array_map('intval', $validated['modifier_group_ids'] ?? []),
            'recipe' => [
                'yield_qty' => (float) ($validated['recipe_yield_qty'] ?? 1),
                'ingredients' => $recipeRows,
            ],
        ];
    }

    private function syncRecipe(Item $item, array $recipeData): void
    {
        if ($item->item_type !== Item::TYPE_MENU || empty($recipeData['ingredients'])) {
            $item->recipe()->delete();

            return;
        }

        $recipe = Recipe::updateOrCreate(
            ['menu_item_id' => $item->id],
            ['yield_qty' => max(0.0001, $recipeData['yield_qty'])]
        );

        $recipe->ingredients()->delete();
        foreach ($recipeData['ingredients'] as $row) {
            $recipe->ingredients()->create([
                'ingredient_item_id' => (int) $row['ingredient_item_id'],
                'quantity' => (float) $row['quantity'],
                'unit_id' => ! empty($row['unit_id']) ? (int) $row['unit_id'] : null,
            ]);
        }

        // Perbarui harga_beli estimasi dari HPP resep jika menu tidak melacak stok sendiri.
        if (! $item->track_stock) {
            $item->update(['harga_beli' => $item->calculateRecipeCogs()]);
        }
    }
}

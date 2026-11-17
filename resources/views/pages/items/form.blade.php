@extends('layouts.app')

@php
    $isEdit = $item->exists;
    $value = fn (string $field) => old($field, $item->{$field});
    $supplierRows = old('suppliers', $suppliers ?: [['id' => null, 'supplier_id' => '', 'harga_beli' => '', 'supplier_sku' => '', 'current_stock' => 0]]);
    $imageUrl = $item->image ? \Illuminate\Support\Facades\Storage::disk('public')->url($item->image) : null;
    $invalid = fn (string $field) => $errors->has($field) ? 'true' : null;
@endphp

@section('content')
    <x-common.page-breadcrumb :pageTitle="$isEdit ? 'Ubah Master Barang' : 'Tambah Master Barang'"
        :parents="[['label' => 'Master Barang', 'url' => route('items.index')]]" />

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-error-200 bg-error-50 px-5 py-4 text-sm text-error-600 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
            Periksa kembali isian yang ditandai merah.
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('items.update', $item) : route('items.store') }}" enctype="multipart/form-data"
        x-data="{
            rows: @js(array_values($supplierRows)),
            add() {
                this.rows.push({ id: null, supplier_id: '', harga_beli: '', supplier_sku: '', current_stock: 0 });
            },
            remove(index) {
                if (this.rows.length > 1) this.rows.splice(index, 1);
            },
            takenElsewhere(supplierId, index) {
                return this.rows.some((row, i) => i !== index && String(row.supplier_id) === String(supplierId));
            },
        }">
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Kolom kiri --}}
            <div class="space-y-6 lg:col-span-2">
                <x-common.component-card title="Informasi Produk">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <x-common.form.field label="Nama" for="name" required class="sm:col-span-2">
                            <x-ui.input id="name" name="name" value="{{ $value('name') }}" required autofocus :aria-invalid="$invalid('name')" />
                        </x-common.form.field>

                        <x-common.form.field label="Kode" for="code" required hint="Dibuat otomatis">
                            <x-ui.input id="code" value="{{ $item->code }}" readonly class="bg-gray-50 dark:bg-white/3" />
                        </x-common.form.field>

                        {{-- Barcode: ketik/scan dengan scanner USB, atau scan lewat kamera --}}
                        <x-common.form.field label="Barcode" for="barcode"
                            x-data="{
                                barcode: {{ \Illuminate\Support\Js::from($value('barcode')) }},
                                scanOpen: false,
                                scanner: null,
                                scanError: '',
                                async loadLibrary() {
                                    if (window.Html5Qrcode) return;
                                    await new Promise((resolve, reject) => {
                                        const script = document.createElement('script');
                                        script.src = '{{ asset('js/html5-qrcode.min.js') }}';
                                        script.onload = resolve;
                                        script.onerror = reject;
                                        document.head.appendChild(script);
                                    });
                                },
                                async openScanner() {
                                    this.scanError = '';
                                    this.scanOpen = true;
                                    try {
                                        await this.loadLibrary();
                                        await this.$nextTick();
                                        this.scanner = new Html5Qrcode('barcode-reader');
                                        await this.scanner.start(
                                            { facingMode: 'environment' },
                                            { fps: 10, qrbox: { width: 260, height: 160 } },
                                            (text) => { this.barcode = text; this.closeScanner(); },
                                        );
                                    } catch (e) {
                                        this.scanError = 'Kamera tidak bisa dibuka. Pastikan izin kamera diberikan dan halaman dibuka lewat HTTPS/localhost.';
                                    }
                                },
                                async closeScanner() {
                                    this.scanOpen = false;
                                    if (this.scanner) {
                                        try { await this.scanner.stop(); this.scanner.clear(); } catch (e) {}
                                        this.scanner = null;
                                    }
                                },
                            }">
                            <div class="flex gap-2">
                                <x-ui.input id="barcode" name="barcode" x-model="barcode" placeholder="Scan atau ketik barcode..." :aria-invalid="$invalid('barcode')"
                                    x-on:keydown.enter.prevent />
                                <x-ui.button variant="outline" size="icon-lg" class="shrink-0" aria-label="Scan dengan kamera" x-on:click="openScanner()">
                                    <x-svg.camera class="size-5" />
                                </x-ui.button>
                            </div>

                            <x-common.modal show="scanOpen" close="closeScanner()" title="Scan Barcode" max-width="md">
                                <div id="barcode-reader" class="overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800"></div>
                                <p x-show="scanError" x-text="scanError" class="mt-3 text-sm text-error-500"></p>
                                <x-slot:footer>
                                    <x-ui.button variant="outline" x-on:click="closeScanner()">Tutup</x-ui.button>
                                </x-slot:footer>
                            </x-common.modal>
                        </x-common.form.field>

                        <x-common.form.field label="Deskripsi" for="description" class="sm:col-span-2">
                            <x-ui.textarea id="description" name="description" rows="3">{{ $value('description') }}</x-ui.textarea>
                        </x-common.form.field>
                    </div>
                </x-common.component-card>

                <x-common.component-card :title="$hasWholesale ? 'Harga Jual & Pengaturan Grosir' : ($usesSuppliers ? 'Harga Jual' : 'Harga')"
                    :desc="$usesSuppliers ? 'Harga jual berlaku sama untuk semua supplier' : 'Harga jual ke pelanggan dan harga beli (modal) barang'">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <x-common.form.field label="Harga Jual Retail" for="harga_jual_retail" required>
                            <x-common.form.money id="harga_jual_retail" name="harga_jual_retail" :value="$value('harga_jual_retail')" :invalid="$errors->has('harga_jual_retail')" />
                        </x-common.form.field>

                        {{-- Modul tanpa supplier (FNB): HPP diisi langsung --}}
                        @unless ($usesSuppliers)
                            <x-common.form.field label="Harga Beli (HPP)" for="harga_beli" required hint="Modal per satuan, untuk menghitung laba">
                                <x-common.form.money id="harga_beli" name="harga_beli" :value="$value('harga_beli')" :invalid="$errors->has('harga_beli')" />
                            </x-common.form.field>
                        @endunless

                        @if ($hasWholesale)
                            <x-common.form.field label="Harga Jual Grosir" for="harga_jual_grosir" required>
                                <x-common.form.money id="harga_jual_grosir" name="harga_jual_grosir" :value="$value('harga_jual_grosir')" :invalid="$errors->has('harga_jual_grosir')" />
                            </x-common.form.field>

                            <x-common.form.field label="Min Qty Grosir" for="min_grosir_qty" required>
                                <x-ui.input type="number" id="min_grosir_qty" name="min_grosir_qty" value="{{ $value('min_grosir_qty') }}" min="0" required
                                    :aria-invalid="$invalid('min_grosir_qty')" />
                            </x-common.form.field>
                        @endif
                    </div>
                </x-common.component-card>

                {{-- Pajak per barang (modul FNB): opsional, kosong = ikut master Pajak & Biaya Layanan --}}
                @if ($usesTaxes)
                    <x-common.component-card title="Pajak" desc="Opsional. Kosongkan untuk mengikuti master Pajak & Biaya Layanan.">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2" x-data="{ rate: @js(old('tax_rate', $item->tax_rate !== null ? (float) $item->tax_rate : '')) }">
                            <x-common.form.field label="Persentase Pajak (%)" for="tax_rate">
                                <div class="relative">
                                    <x-ui.input type="number" id="tax_rate" name="tax_rate" x-model="rate" min="0" max="100" step="0.01" placeholder="Ikut master"
                                        class="pe-10" :aria-invalid="$invalid('tax_rate')" />
                                    <span class="pointer-events-none absolute end-4 top-1/2 -translate-y-1/2 text-sm text-gray-400">%</span>
                                </div>
                            </x-common.form.field>
                            <x-common.form.field label="Tipe Pajak" for="tax_method" hint="Inclusive = sudah termasuk di harga jual; Exclusive = ditambahkan saat bayar.">
                                <x-common.form.select id="tax_method" name="tax_method" :options="['exclusive' => 'Exclusive', 'inclusive' => 'Inclusive']"
                                    :value="old('tax_method', $item->tax_method ?? 'exclusive')" x-bind:disabled="rate === '' || rate === null"
                                    :aria-invalid="$invalid('tax_method')" />
                            </x-common.form.field>
                        </div>
                    </x-common.component-card>
                @endif

                @if ($usesModifiers)
                    <x-common.component-card title="Modifier & Topping" desc="Pilih grup opsi tambahan (mis. Level Gula, Topping) yang muncul saat kasir memilih menu ini.">
                        @php
                            $selectedModifiers = collect(old('modifier_group_ids', $item->relationLoaded('modifierGroups') ? $item->modifierGroups->pluck('id')->all() : []))
                                ->map(fn ($id) => (int) $id)
                                ->all();
                        @endphp
                        @if ($modifierGroupOptions->isEmpty())
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Belum ada grup modifier aktif. Tambah di menu <a href="{{ route('modifiers.index') }}" class="text-brand-600 underline dark:text-brand-400">Modifier & Topping</a>.
                            </p>
                        @else
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @foreach ($modifierGroupOptions as $group)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-gray-200 p-3 transition hover:border-brand-300 dark:border-gray-800 dark:hover:border-brand-500/40">
                                        <input type="checkbox" name="modifier_group_ids[]" value="{{ $group->id }}"
                                            @checked(in_array($group->id, $selectedModifiers, true))
                                            class="size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500" />
                                        <span class="text-sm font-medium text-gray-800 dark:text-white/90">{{ $group->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </x-common.component-card>
                @endif

                @if ($usesRecipes)
                    @php
                        $recipeRows = old('recipe_ingredients', $item->relationLoaded('recipe') && $item->recipe
                            ? $item->recipe->ingredients->map(fn ($r) => [
                                'ingredient_item_id' => $r->ingredient_item_id,
                                'quantity' => (float) $r->quantity,
                                'unit_id' => $r->unit_id,
                            ])->values()->all()
                            : []);
                    @endphp
                    <x-common.component-card title="Tipe Menu & Resep (BOM)" desc="Atur apakah item ini Menu Jadi (dengan resep pemotong stok bahan baku), Bahan Baku Dapur, atau Barang Jual Langsung.">
                        <div class="space-y-4" x-data="{
                                itemType: @js(old('item_type', $item->item_type ?? \App\Models\Item::TYPE_MENU)),
                                trackStock: @js((bool) old('track_stock', $item->exists ? $item->track_stock : false)),
                                rows: @js($recipeRows),
                                addRow() { this.rows.push({ ingredient_item_id: '', quantity: '', unit_id: '' }); },
                            }">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <x-common.form.field label="Tipe Item" for="item_type">
                                    <select id="item_type" name="item_type" x-model="itemType"
                                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                        @foreach (\App\Models\Item::TYPES as $val => $label)
                                            <option value="{{ $val }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </x-common.form.field>
                                <div x-show="itemType === 'menu'" class="flex items-end pb-2">
                                    <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input type="checkbox" name="track_stock" value="1" x-model="trackStock"
                                            class="size-4 rounded border-gray-300 text-brand-500" />
                                        <span>Lacak stok menu sendiri (jangan potong bahan baku)</span>
                                    </label>
                                </div>
                            </div>

                            <div x-show="itemType === 'menu' && !trackStock" class="space-y-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div>
                                        <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90">Komposisi Bahan Baku</h4>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Stok bahan baku otomatis terpotong saat menu terjual di kasir.</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <label class="text-xs text-gray-500 dark:text-gray-400">Hasil (Porsi):</label>
                                        <input type="number" name="recipe_yield_qty" step="0.01" min="0.01"
                                            value="{{ old('recipe_yield_qty', $item->recipe?->yield_qty ?? 1) }}"
                                            class="h-9 w-20 rounded-lg border border-gray-300 bg-transparent px-2 text-center text-sm dark:border-gray-700 dark:text-white/90" />
                                        <x-ui.button type="button" size="sm" variant="outline" x-on:click="addRow()">+ Bahan</x-ui.button>
                                    </div>
                                </div>

                                @if ($ingredientOptions->isEmpty())
                                    <p class="text-xs text-warning-600 dark:text-warning-400">Belum ada item bertipe <strong>Bahan Baku</strong>. Buat item bahan baku terlebih dahulu.</p>
                                @endif

                                <template x-for="(row, idx) in rows" :key="idx">
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_110px_130px_auto] items-center">
                                        <select x-bind:name="`recipe_ingredients[${idx}][ingredient_item_id]`" x-model="row.ingredient_item_id"
                                            class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            <option value="">-- Pilih Bahan Baku --</option>
                                            @foreach ($ingredientOptions as $ing)
                                                <option value="{{ $ing->id }}">{{ $ing->name }} ({{ $ing->unit?->name }})</option>
                                            @endforeach
                                        </select>
                                        <input type="number" step="0.0001" min="0.0001" x-bind:name="`recipe_ingredients[${idx}][quantity]`" x-model="row.quantity" placeholder="Takaran"
                                            class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white/90" />
                                        <select x-bind:name="`recipe_ingredients[${idx}][unit_id]`" x-model="row.unit_id"
                                            class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                                            <option value="">Satuan Dasar</option>
                                            @foreach ($unitOptions as $uid => $uname)
                                                <option value="{{ $uid }}">{{ $uname }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" x-on:click="rows.splice(idx, 1)" class="rounded-lg p-2 text-error-500 hover:bg-error-50 dark:hover:bg-error-500/10" title="Hapus">
                                            <x-svg.trash-bin class="size-4" />
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </x-common.component-card>
                @endif

                @if ($usesSuppliers)
                <x-common.component-card title="Supplier & Harga Beli" desc="Satu barang bisa disuplai oleh banyak supplier dengan harga beli yang berbeda">
                    @error('suppliers')
                        <p class="text-sm text-error-500">{{ $message }}</p>
                    @enderror

                    <template x-for="(row, index) in rows" :key="index">
                        <div class="relative rounded-2xl border border-gray-200 p-5 dark:border-gray-800">
                            <input type="hidden" x-bind:name="`suppliers[${index}][id]`" x-bind:value="row.id ?? ''">

                            <div class="mb-4 flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500 dark:text-gray-400" x-text="`Supplier ${index + 1}`"></span>
                                <x-ui.button size="icon-sm" variant="ghost" aria-label="Hapus supplier" class="text-error-500 hover:text-error-600"
                                    x-show="rows.length > 1" x-on:click="remove(index)">
                                    <x-svg.trash-bin class="size-5" />
                                </x-ui.button>
                            </div>

                            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                                <x-common.form.field label="Supplier" required class="sm:col-span-2">
                                    <select class="form-select" x-bind:name="`suppliers[${index}][supplier_id]`" x-model="row.supplier_id" required>
                                        <option value="">Pilih supplier</option>
                                        @foreach ($supplierOptions as $id => $name)
                                            <option value="{{ $id }}" x-bind:disabled="takenElsewhere({{ $id }}, index)">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </x-common.form.field>

                                <x-common.form.field label="Harga Beli" required>
                                    <x-common.form.money model="row.harga_beli" bind-name="`suppliers[${index}][harga_beli]`" />
                                </x-common.form.field>

                                <x-common.form.field label="SKU Supplier">
                                    <x-ui.input x-bind:name="`suppliers[${index}][supplier_sku]`" x-model="row.supplier_sku" placeholder="Kode internal supplier" />
                                </x-common.form.field>

                                <x-common.form.field label="Stok Supplier" hint="Dikelola via transaksi" class="sm:col-span-2">
                                    <x-ui.input type="number" x-bind:value="row.current_stock ?? 0" disabled />
                                </x-common.form.field>
                            </div>
                        </div>
                    </template>

                    {{-- Pesan validasi per baris supplier --}}
                    @foreach ($errors->getMessages() as $key => $messages)
                        @if (str_starts_with($key, 'suppliers.'))
                            <p class="text-sm text-error-500">Supplier {{ (int) explode('.', $key)[1] + 1 }}: {{ $messages[0] }}</p>
                        @endif
                    @endforeach

                    <x-ui.button variant="outline" class="w-full" x-on:click="add()">
                        <x-svg.plus class="size-5" />
                        Tambah Supplier
                    </x-ui.button>
                </x-common.component-card>
                @endif
            </div>

            {{-- Kolom kanan --}}
            <div class="space-y-6">
                <x-common.component-card :title="$hasImage ? 'Status & Gambar' : 'Status'">
                    <div x-data="{ active: @js((bool) old('is_active', $item->is_active ?? true)) }">
                        <x-common.form.toggle name="is_active" model="active" label="Aktif" />
                    </div>

                    @if ($hasImage)
                        <x-common.form.field label="Gambar" for="image" error="image" x-data="{ remove: false, preview: null }">
                            @if ($imageUrl)
                                <div x-show="!remove && !preview" class="mb-2 flex items-center gap-3">
                                    <img src="{{ $imageUrl }}" alt="{{ $item->name }}" class="size-20 rounded-xl border border-gray-200 object-cover dark:border-gray-800" />
                                    <x-ui.button size="xs" variant="outline" x-on:click="remove = true">Hapus gambar</x-ui.button>
                                </div>
                                <input type="hidden" name="remove_image" x-bind:value="remove ? 1 : 0">
                            @endif
                            <img x-show="preview" x-bind:src="preview" alt="Pratinjau" class="mb-2 size-20 rounded-xl border border-gray-200 object-cover dark:border-gray-800" x-cloak />
                            <x-ui.file-input id="image" name="image" accept="image/*"
                                x-on:change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null" />
                        </x-common.form.field>
                    @endif
                </x-common.component-card>

                <x-common.component-card title="Klasifikasi">
                    @if ($hasBrand)
                        <x-common.form.field label="Merk" for="brand_id">
                            <x-common.form.select id="brand_id" name="brand_id" :options="$brandOptions" placeholder="Pilih opsi" :value="$value('brand_id')" />
                        </x-common.form.field>
                    @endif

                    <x-common.form.field label="Kategori" for="category_id" required>
                        <x-common.form.select id="category_id" name="category_id" :options="$categoryOptions" placeholder="Pilih opsi" :value="$value('category_id')" required
                            :aria-invalid="$invalid('category_id')" />
                    </x-common.form.field>

                    <x-common.form.field label="Satuan" for="unit_id" required>
                        <x-common.form.select id="unit_id" name="unit_id" :options="$unitOptions" placeholder="Pilih opsi" :value="$value('unit_id')" required
                            :aria-invalid="$invalid('unit_id')" />
                    </x-common.form.field>
                </x-common.component-card>

                <x-common.component-card title="Stok & Lokasi">
                    <x-common.form.field label="Total Stok" for="current_stock" required hint="Agregat dari semua supplier">
                        <x-ui.input type="number" id="current_stock" value="{{ $item->current_stock ?? 0 }}" disabled />
                    </x-common.form.field>

                    <x-common.form.field label="Min Stok" for="min_stock" required>
                        <x-ui.input type="number" id="min_stock" name="min_stock" value="{{ $value('min_stock') }}" min="0" required :aria-invalid="$invalid('min_stock')" />
                    </x-common.form.field>

                    <x-common.form.field label="Lokasi Rak" for="rack_location">
                        <x-ui.input id="rack_location" name="rack_location" value="{{ $value('rack_location') }}" />
                    </x-common.form.field>
                </x-common.component-card>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-3">
            @if ($isEdit)
                <x-ui.button type="submit">Simpan</x-ui.button>
            @else
                <x-ui.button type="submit">Tambah</x-ui.button>
                <x-ui.button type="submit" variant="outline" name="create_another" value="1">Tambah &amp; buat lagi</x-ui.button>
            @endif
            <x-ui.button as="a" href="{{ route('items.index') }}" variant="outline">Batal</x-ui.button>
        </div>
    </form>
@endsection

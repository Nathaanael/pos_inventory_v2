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

                <x-common.component-card :title="$hasWholesale ? 'Harga Jual & Pengaturan Grosir' : 'Harga Jual'" desc="Harga jual berlaku sama untuk semua supplier">
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                        <x-common.form.field label="Harga Jual Retail" for="harga_jual_retail" required>
                            <x-common.form.money id="harga_jual_retail" name="harga_jual_retail" :value="$value('harga_jual_retail')" :invalid="$errors->has('harga_jual_retail')" />
                        </x-common.form.field>

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

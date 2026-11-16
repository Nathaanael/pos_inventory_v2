@extends('layouts.app')
@use('App\Enums\BusinessType')
@use('App\Enums\Feature')

{{-- Pengaturan Toko (dulu App\Filament\Pages\StoreSettings). --}}
@php
    $value = fn (string $field) => old($field, $setting->{$field});
    $invalid = fn (string $field) => $errors->has($field) ? 'true' : null;
    $businesses = collect(BusinessType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]);
    $presets = collect(BusinessType::cases())->mapWithKeys(fn ($t) => [$t->value => array_map(fn ($f) => $f->value, $t->defaultFeatures())]);
    $featureList = collect(Feature::cases())->map(fn ($f) => [
        'value' => $f->value,
        'label' => $f->label(),
        'description' => $f->description(),
        'only' => $f->businessTypes() ? array_map(fn ($b) => $b->value, $f->businessTypes()) : null,
    ])->values();
    $logoUrl = $setting->logoUrl();
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Pengaturan Toko" />

    @include('pages.inventory.partials.form-errors')

    <form method="POST" action="{{ route('store-settings.update') }}" enctype="multipart/form-data" class="space-y-6"
        x-data="{
            businessType: @js(old('business_type', $setting->business_type?->value)),
            features: @js(old('features', $enabledFeatures)),
            presets: @js($presets),
            featureList: @js($featureList),
            get available() {
                return this.featureList.filter((f) => !f.only || (this.businessType && f.only.includes(this.businessType)));
            },
            toggle(value) {
                this.features = this.features.includes(value) ? this.features.filter((f) => f !== value) : [...this.features, value];
            },
            applyPreset() {
                if (this.presets[this.businessType]) this.features = [...this.presets[this.businessType]];
            },
            get allOn() { return this.available.every((f) => this.features.includes(f.value)); },
            toggleAll() {
                const values = this.available.map((f) => f.value);
                this.features = this.allOn ? this.features.filter((f) => !values.includes(f)) : [...new Set([...this.features, ...values])];
            },
        }">
        @csrf
        @method('PUT')

        {{-- Identitas Usaha --}}
        <x-common.component-card title="Identitas Usaha" desc="Tampil di panel admin, struk penjualan, dan laporan Excel.">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-common.form.field label="Nama Usaha" for="store_name" required>
                    <x-ui.input id="store_name" name="store_name" value="{{ $value('store_name') }}" maxlength="100" required :aria-invalid="$invalid('store_name')" />
                </x-common.form.field>
                <x-common.form.field label="Keterangan Singkat" for="tagline">
                    <x-ui.input id="tagline" name="tagline" value="{{ $value('tagline') }}" maxlength="150" placeholder="mis. Toko Bangunan & Listrik" />
                </x-common.form.field>

                <x-common.form.field label="Logo" for="logo" class="sm:col-span-2"
                    hint="Opsional. Maks 1 MB. Bila ada logo, nama toko tidak ditampilkan lagi di sidebar & header."
                    x-data="{ preview: {{ \Illuminate\Support\Js::from($logoUrl) }}, remove: false }">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex h-24 min-w-24 items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 p-2 dark:border-gray-700 dark:bg-white/3">
                            <img x-show="preview && !remove" x-bind:src="preview" alt="Logo" class="max-h-20 max-w-60 object-contain" />
                            <span x-show="!preview || remove" class="px-3 text-xs text-gray-400">Belum ada logo</span>
                        </div>
                        <div class="flex flex-col gap-2">
                            <x-ui.file-input id="logo" name="logo" accept="image/*" class="max-w-xs"
                                x-on:change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : preview; remove = false" />
                            @if ($logoUrl)
                                <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                    <input type="checkbox" name="remove_logo" value="1" x-model="remove"
                                        class="size-4 rounded border-gray-300 text-error-500 dark:border-gray-700 dark:bg-gray-900" />
                                    Hapus logo
                                </label>
                            @endif
                        </div>
                    </div>
                </x-common.form.field>
            </div>
        </x-common.component-card>

        {{-- Jenis Usaha & Fitur --}}
        <x-common.component-card title="Jenis Usaha & Fitur" desc="Mematikan fitur hanya menyembunyikan menu dan kolomnya; data yang sudah ada tidak dihapus.">
            <x-common.form.field label="Jenis Usaha" for="business_type" required class="sm:max-w-sm">
                <x-common.form.select id="business_type" name="business_type" :options="$businesses" placeholder="Pilih jenis usaha" required x-model="businessType" />
            </x-common.form.field>

            <div>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-400">Fitur Aktif</span>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-ui.button size="xs" variant="outline" x-on:click="toggleAll()" x-text="allOn ? 'Batalkan semua' : 'Pilih semua'"></x-ui.button>
                        <x-ui.button size="xs" variant="outline" x-bind:disabled="!presets[businessType]" x-on:click="applyPreset()">
                            <svg class="size-4 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                            Pakai fitur bawaan jenis usaha
                        </x-ui.button>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <template x-for="f in available" :key="f.value">
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition"
                            x-bind:class="features.includes(f.value) ? 'border-brand-300 bg-brand-50/60 dark:border-brand-500/40 dark:bg-brand-500/5' : 'border-gray-200 hover:border-gray-300 dark:border-gray-800'">
                            <input type="checkbox" name="features[]" x-bind:value="f.value" x-bind:checked="features.includes(f.value)" x-on:change="toggle(f.value)"
                                class="mt-0.5 size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900" />
                            <span>
                                <span class="block text-sm font-medium text-gray-800 dark:text-white/90" x-text="f.label"></span>
                                <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="f.description"></span>
                            </span>
                        </label>
                    </template>
                </div>
            </div>
        </x-common.component-card>

        {{-- Kontak & Alamat --}}
        <x-common.component-card title="Kontak & Alamat" desc="Dicetak di bagian atas struk.">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-common.form.field label="Alamat" for="address" class="sm:col-span-2">
                    <x-ui.textarea id="address" name="address" rows="2">{{ $value('address') }}</x-ui.textarea>
                </x-common.form.field>
                <x-common.form.field label="Telepon / WhatsApp" for="phone">
                    <x-ui.input type="tel" id="phone" name="phone" value="{{ $value('phone') }}" maxlength="30" />
                </x-common.form.field>
                <x-common.form.field label="Email" for="email">
                    <x-ui.input type="email" id="email" name="email" value="{{ $value('email') }}" maxlength="100" :aria-invalid="$invalid('email')" />
                </x-common.form.field>
                <x-common.form.field label="NPWP" for="tax_number">
                    <x-ui.input id="tax_number" name="tax_number" value="{{ $value('tax_number') }}" maxlength="50" />
                </x-common.form.field>
            </div>
        </x-common.component-card>

        {{-- Struk & Printer --}}
        <x-common.component-card title="Struk & Printer">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <x-common.form.field label="Teks Penutup Struk" for="receipt_footer">
                    <x-ui.input id="receipt_footer" name="receipt_footer" value="{{ $value('receipt_footer') }}" maxlength="255" placeholder="Terima Kasih" />
                </x-common.form.field>
                <x-common.form.field label="Nama Printer Thermal" for="printer_name"
                    hint="Nama printer persis seperti di Windows (Settings > Printers). Kosongkan untuk memakai THERMAL_PRINTER_NAME di .env.">
                    <x-ui.input id="printer_name" name="printer_name" value="{{ $value('printer_name') }}" maxlength="100" placeholder="RP58 Printer" />
                </x-common.form.field>
            </div>
        </x-common.component-card>

        <x-ui.button type="submit">Simpan Pengaturan</x-ui.button>
    </form>
@endsection

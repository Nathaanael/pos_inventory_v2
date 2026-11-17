@extends('layouts.app')
@use('App\Enums\Feature')

{{-- Pengaturan Toko (dulu App\Filament\Pages\StoreSettings). --}}
@php
    $value = fn (string $field) => old($field, $setting->{$field});
    $invalid = fn (string $field) => $errors->has($field) ? 'true' : null;
    $business = $setting->business_type;
    // Fitur aktif = fitur bawaan modul jenis usaha (tidak bisa dicentang di sini).
    $moduleFeatures = collect(Feature::cases())->filter(fn ($f) => in_array($f->value, $enabledFeatures, true));
    $logoUrl = $setting->logoUrl();
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Pengaturan Toko" />

    @include('pages.inventory.partials.form-errors')

    {{-- AJAX + loading bar; form & logo/nama toko di sidebar/header (data-ajax-region="store-brand") diperbarui. --}}
    <form id="store-settings-form" method="POST" action="{{ route('store-settings.update') }}" enctype="multipart/form-data" class="space-y-6"
        x-data="ajaxForm({ regions: ['store-settings-form', 'store-brand'] })" x-on:submit.prevent="submit">
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

        {{-- Jenis Usaha & Fitur (hanya tampilan; diganti lewat wizard setup) --}}
        <x-common.component-card title="Jenis Usaha & Fitur" desc="Jenis usaha menentukan modul dan fitur aplikasi. Fitur mengikuti modul dan tidak bisa diatur satu per satu.">
            <div class="flex flex-col gap-4 rounded-2xl border border-gray-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                <div class="flex items-center gap-4">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                        <svg class="size-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $business?->iconPath() }}" /></svg>
                    </span>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Jenis Usaha</p>
                        <p class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $business?->label() ?? 'Belum dipilih' }}</p>
                        @if ($business)
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $business->description() }}</p>
                        @endif
                    </div>
                </div>
                <x-ui.button variant="outline" class="shrink-0"
                    x-data x-on:click="$dispatch('confirm', {
                        title: 'Ganti Jenis Usaha',
                        message: 'Wizard setup akan dibuka lagi untuk memilih jenis usaha. Selama wizard belum diselesaikan, kasir tidak bisa berjualan. Data barang & transaksi tidak dihapus.',
                        action: {{ \Illuminate\Support\Js::from(route('setup.restart')) }},
                        method: 'POST',
                        confirmLabel: 'Buka Wizard',
                        variant: 'default',
                    })">
                    <svg class="size-4 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                    Ganti Jenis Usaha
                </x-ui.button>
            </div>

            <div>
                <p class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-400">Fitur Modul</p>
                @if ($moduleFeatures->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada fitur aktif.</p>
                @else
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($moduleFeatures as $feature)
                            <div class="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/3">
                                <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-success-500 text-white">
                                    <x-svg.check class="size-3" />
                                </span>
                                <span>
                                    <span class="block text-sm font-medium text-gray-800 dark:text-white/90">{{ $feature->label() }}</span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $feature->description() }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
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

        @if (\App\Models\StoreSetting::usesCashCount())
            {{-- Hitung Kas (modul FNB) --}}
            <x-common.component-card title="Hitung Kas" desc="Uang fisik di laci dibandingkan dengan uang di sistem setiap tutup shift dan cek dadakan.">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-common.form.field label="Toleransi Selisih Kas" for="cash_count_tolerance"
                        hint="Selisih sampai nominal ini dianggap cocok. Di atasnya, hitung kas perlu direview admin dengan catatan penjelasan.">
                        <x-common.form.money id="cash_count_tolerance" name="cash_count_tolerance" :value="$value('cash_count_tolerance') ?? 0" :invalid="$errors->has('cash_count_tolerance')" />
                    </x-common.form.field>
                </div>
            </x-common.component-card>
        @endif

        <x-ui.button type="submit" x-bind:disabled="busy">
                        <span x-text="busy ? 'Menyimpan…' : 'Simpan Pengaturan'">Simpan Pengaturan</span>
                    </x-ui.button>
    </form>
@endsection

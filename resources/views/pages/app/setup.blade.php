@extends('layouts.fullscreen-layout')
@use('App\Enums\BusinessType')
@use('App\Enums\Feature')
@use('App\Http\Controllers\App\SetupController')

{{--
    Wizard setup toko. Langkah dikelola Alpine; dikirim sekali saat "Mulai Gunakan".
    Tampilan: kartu putih di atas latar biru tua — panel kiri (merek + pelacak langkah),
    panel kanan abu muda (judul, pilihan berupa kartu 2 kolom, tombol Kembali/Lanjut).
--}}
@php
    $existingLogo = $setting->logoUrl();
    $storeLabel = \App\Models\StoreSetting::storeName();
    $headings = [
        1 => ['Identitas Toko', 'Nama dan kontak ini tampil di aplikasi dan dicetak di struk.'],
        2 => ['Jenis Usaha', 'Usaha apa yang Anda jalankan? Aplikasi akan memakai modul sesuai jenis usaha ini.'],
        3 => ['Fitur', 'Fitur bawaan modul jenis usaha yang Anda pilih.'],
        4 => ['Ringkasan', 'Periksa kembali sebelum mulai menggunakan aplikasi.'],
    ];
    // Jenis usaha = modul; fitur tiap modul tetap (tidak bisa dicentang).
    $businesses = collect(BusinessType::cases())->map(fn ($t) => [
        'value' => $t->value, 'label' => $t->label(), 'description' => $t->description(), 'icon' => $t->iconPath(),
        'features' => array_map(fn ($f) => $f->value, $t->features()),
    ])->values();
    $features = collect(Feature::cases())->map(fn ($f) => [
        'value' => $f->value, 'label' => $f->label(), 'description' => $f->description(), 'icon' => $f->iconPath(),
    ])->values();
    $storeName = old('store_name', $setting->store_name === \App\Models\StoreSetting::DEFAULT_NAME ? '' : $setting->store_name);

    // Kartu pilihan (gaya referensi): putih, ikon kotak abu, border biru + lencana centang saat dipilih.
    $cardBase = 'group relative flex w-full items-center gap-3.5 rounded-xl border bg-white p-4 text-start shadow-theme-xs transition dark:bg-gray-900';
    $cardOff = "'border-transparent hover:border-gray-200 dark:hover:border-gray-700'";
    $cardOn = "'border-brand-500 ring-1 ring-brand-500'";
@endphp

@section('content')
    <div class="flex min-h-screen items-center justify-center bg-gradient-to-br from-brand-800 via-brand-700 to-brand-800 p-4 sm:p-8">
        <form method="POST" action="{{ route('setup.store') }}" enctype="multipart/form-data"
            class="grid w-full max-w-6xl overflow-hidden rounded-2xl bg-white shadow-theme-xl lg:grid-cols-[280px_1fr] dark:bg-gray-900"
            x-on:keydown.enter="if (step < 4 && $event.target.tagName !== 'TEXTAREA') { $event.preventDefault(); next(); }"
            x-on:submit="if (submitting) { $event.preventDefault(); return; } submitting = true"
            x-data="{
                submitting: false,
                step: {{ $errors->any() ? $errorStep : 1 }},
                reached: {{ $errors->any() ? 4 : 1 }},
                store_name: @js($storeName),
                tagline: @js(old('tagline', $setting->tagline)),
                phone: @js(old('phone', $setting->phone)),
                address: @js(old('address', $setting->address)),
                logoPreview: @js($existingLogo),
                {{-- Saat "Ganti Jenis Usaha", jenis usaha lama sudah terpilih; user bisa memilih yang lain. --}}
                businessType: @js(old('business_type', $setting->business_type?->value)),
                businesses: @js($businesses),
                featureList: @js($features),
                stepError: '',
                tip: null,
                get selected() { return this.businesses.find((b) => b.value === this.businessType) ?? null; },
                get moduleFeatures() {
                    const values = this.selected?.features ?? [];
                    return this.featureList.filter((f) => values.includes(f.value));
                },
                pick(type) {
                    this.businessType = type;
                    this.stepError = '';
                },
                validateStep() {
                    this.stepError = '';
                    if (this.step === 1 && !this.store_name.trim()) { this.stepError = 'Nama toko wajib diisi.'; return false; }
                    if (this.step === 2 && !this.businessType) { this.stepError = 'Pilih salah satu jenis usaha.'; return false; }
                    return true;
                },
                next() {
                    if (!this.validateStep()) return;
                    this.step++;
                    this.reached = Math.max(this.reached, this.step);
                },
                goTo(n) { if (n < this.step || n <= this.reached) { this.step = n; this.stepError = ''; } },
                previewLogo(event) {
                    const file = event.target.files[0];
                    this.logoPreview = file ? URL.createObjectURL(file) : @js($existingLogo);
                },
            }">
            @csrf
            <input type="hidden" name="business_type" x-bind:value="businessType ?? ''">

            {{-- ===================== KIRI: merek + pelacak langkah ===================== --}}
            <aside class="flex flex-col border-b border-gray-100 bg-white p-8 lg:border-e lg:border-b-0 dark:border-gray-800 dark:bg-gray-900">
                <div class="flex h-10 items-center">
                    @if ($existingLogo)
                        <img src="{{ $existingLogo }}" alt="{{ $storeLabel }}" class="max-h-10 w-auto max-w-44 object-contain" />
                    @else
                        <span class="flex items-center gap-2 text-lg font-bold text-brand-700 dark:text-brand-400">
                            <span class="flex size-9 items-center justify-center rounded-lg bg-brand-500 text-white"><x-svg.gear class="size-5" /></span>
                            {{ $storeLabel }}
                        </span>
                    @endif
                </div>

                <div class="mt-12">
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Setup Toko</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Siapkan toko Anda dalam beberapa langkah.</p>
                </div>

                <ol class="mt-10 space-y-1">
                    @foreach (SetupController::STEPS as $number => $label)
                        <li>
                            <button type="button" x-on:click="goTo({{ $number }})" x-bind:disabled="{{ $number }} > reached"
                                class="flex w-full items-center gap-3 py-2.5 text-start text-sm transition disabled:cursor-default">
                                {{-- selesai: centang hijau · aktif: panah · berikutnya: titik abu --}}
                                <span class="flex size-5 shrink-0 items-center justify-center">
                                    <span x-show="{{ $number }} < step" class="flex size-5 items-center justify-center rounded-full bg-success-50 text-success-500 dark:bg-success-500/15">
                                        <x-svg.check class="size-3" />
                                    </span>
                                    <x-svg.arrow-right x-show="step === {{ $number }}" class="size-4 text-gray-800 rtl:rotate-180 dark:text-white/90" />
                                    <span x-show="{{ $number }} > step" class="size-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></span>
                                </span>
                                <span x-bind:class="{
                                        'font-medium text-success-600 dark:text-success-500': {{ $number }} < step,
                                        'font-semibold text-gray-800 dark:text-white/90': step === {{ $number }},
                                        'text-gray-400 dark:text-gray-500': {{ $number }} > step,
                                    }">{{ $label }}</span>
                            </button>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-8 border-t border-gray-100 pt-6 dark:border-gray-800">
                    <p class="flex items-center gap-2.5 text-sm font-medium text-gray-700 dark:text-gray-300">
                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-800 dark:bg-white/5 dark:text-white/90"
                            x-text="moduleFeatures.length">0</span>
                        <span x-text="selected ? 'fitur modul ' + selected.label : 'fitur modul'">fitur modul</span>
                    </p>
                </div>

                <div class="mt-auto pt-12 text-xs text-gray-400 dark:text-gray-500">
                    <p>Semua pengaturan bisa diubah lagi di menu Pengaturan Toko.</p>
                    <p class="mt-2">&copy; {{ now()->year }} {{ $storeLabel }}</p>
                </div>
            </aside>

            {{-- ===================== KANAN: isi langkah ===================== --}}
            <main class="relative flex min-h-[600px] flex-col bg-gray-50 p-6 sm:p-10 lg:px-16 lg:py-12 dark:bg-white/2">
                <div class="absolute end-6 top-6 flex items-center gap-2 sm:end-8 sm:top-8">
                    {{-- Dibuka dari "Ganti Jenis Usaha": boleh batal & kembali tanpa perubahan --}}
                    @if ($setting->business_type)
                        <button type="submit" form="cancel-form" title="Batal ganti jenis usaha"
                            class="flex h-10 items-center gap-1.5 rounded-full border border-gray-300 bg-white px-4 text-sm text-gray-600 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/5">
                            <x-svg.x class="size-4" /> Batal
                        </button>
                    @endif
                    <button type="submit" form="logout-form" title="Keluar dari akun"
                        class="flex h-10 items-center gap-1.5 rounded-full border border-gray-300 bg-white px-4 text-sm text-gray-600 transition hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-white/5">
                        <x-svg.log-out class="size-4" /> Keluar
                    </button>
                </div>

                <div class="max-w-3xl pe-0 sm:pe-48">
                    @foreach ($headings as $number => [$heading, $sub])
                        <div x-show="step === {{ $number }}" @if ($number !== 1) x-cloak @endif>
                            <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">{{ $heading }}</h1>
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $sub }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8 max-w-3xl flex-1">
                    {{-- ---------- 1. Identitas toko ---------- --}}
                    <div x-show="step === 1" class="rounded-xl bg-white p-6 shadow-theme-xs dark:bg-gray-900">
                        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            <x-common.form.field label="Nama toko" for="store_name" required>
                                <x-ui.input id="store_name" name="store_name" x-model="store_name" maxlength="100" placeholder="mis. Toko Makmur Jaya" autofocus
                                    :aria-invalid="$errors->has('store_name') ? 'true' : null" />
                            </x-common.form.field>
                            <x-common.form.field label="Keterangan singkat" for="tagline">
                                <x-ui.input id="tagline" name="tagline" x-model="tagline" maxlength="150" placeholder="mis. Bangunan & Listrik" />
                            </x-common.form.field>
                            <x-common.form.field label="Telepon / WhatsApp" for="phone">
                                <x-ui.input type="tel" id="phone" name="phone" x-model="phone" maxlength="30" placeholder="08xx" />
                            </x-common.form.field>
                            <x-common.form.field label="Logo" for="logo" hint="Opsional, maks 1 MB">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-white/3">
                                        <img x-show="logoPreview" x-bind:src="logoPreview" alt="" class="size-full object-contain" />
                                        <x-svg.upload class="size-5 text-gray-400" x-show="!logoPreview" />
                                    </span>
                                    <x-ui.file-input id="logo" name="logo" accept="image/*" x-on:change="previewLogo($event)" />
                                </div>
                            </x-common.form.field>
                            <x-common.form.field label="Alamat" for="address" class="sm:col-span-2">
                                <x-ui.textarea id="address" name="address" x-model="address" maxlength="500" rows="3" placeholder="Alamat lengkap toko (dicetak di struk)" />
                            </x-common.form.field>
                        </div>
                    </div>

                    {{-- ---------- 2. Jenis usaha (kartu pilihan 2 kolom) ---------- --}}
                    <div x-show="step === 2" x-cloak class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <template x-for="b in businesses" :key="b.value">
                            <div class="relative">
                                <button type="button" x-on:click="pick(b.value)" x-bind:aria-pressed="businessType === b.value"
                                    class="{{ $cardBase }} pe-12" x-bind:class="businessType === b.value ? {{ $cardOn }} : {{ $cardOff }}">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg transition"
                                        x-bind:class="businessType === b.value ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400'">
                                        <svg class="size-5 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" x-bind:d="b.icon" /></svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold"
                                            x-bind:class="businessType === b.value ? 'text-brand-600 dark:text-brand-400' : 'text-gray-800 dark:text-white/90'"
                                            x-text="b.label"></span>
                                        <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400" x-text="b.features.length + ' fitur modul'"></span>
                                    </span>
                                </button>

                                {{-- Info + tooltip deskripsi --}}
                                <button type="button" class="absolute end-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                                    x-on:mouseenter="tip = b.value" x-on:mouseleave="tip = null" x-on:focus="tip = b.value" x-on:blur="tip = null"
                                    x-on:click="tip = tip === b.value ? null : b.value" x-bind:aria-label="'Info ' + b.label">
                                    <x-svg.info-circle class="size-5" />
                                </button>
                                <div x-show="tip === b.value" x-cloak x-transition.opacity
                                    class="absolute end-0 bottom-full z-20 mb-2 w-60 rounded-xl bg-white p-3 text-xs leading-relaxed text-gray-700 shadow-theme-lg dark:bg-gray-800 dark:text-gray-300"
                                    x-text="b.description"></div>

                                {{-- Lencana centang saat dipilih --}}
                                <span x-show="businessType === b.value" x-cloak
                                    class="absolute -end-2 -top-2 flex size-5 items-center justify-center rounded-full bg-brand-500 text-white ring-2 ring-white dark:ring-gray-900">
                                    <x-svg.check class="size-3" />
                                </span>
                            </div>
                        </template>
                    </div>

                    {{-- ---------- 3. Fitur modul (hanya tampilan) ---------- --}}
                    <div x-show="step === 3" x-cloak>
                        <p x-show="selected" class="mb-5 flex items-start gap-2 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-700 dark:bg-brand-500/10 dark:text-brand-400">
                            <x-svg.info-circle class="mt-0.5 size-4 shrink-0" />
                            <span>Modul <strong x-text="selected?.label"></strong> sudah termasuk fitur berikut. Fitur mengikuti jenis usaha dan tidak bisa diubah satu per satu; untuk mengganti, pilih jenis usaha lain.</span>
                        </p>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <template x-for="f in moduleFeatures" :key="f.value">
                                <div class="relative">
                                    <div class="{{ $cardBase }} border-transparent pe-12">
                                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400">
                                            <svg class="size-5 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" x-bind:d="f.icon" /></svg>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-semibold text-gray-800 dark:text-white/90" x-text="f.label"></span>
                                            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400" x-text="f.description"></span>
                                        </span>
                                    </div>
                                    <span class="absolute end-4 top-1/2 flex size-5 -translate-y-1/2 items-center justify-center rounded-full bg-success-500 text-white">
                                        <x-svg.check class="size-3" />
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- ---------- 4. Ringkasan ---------- --}}
                    <dl x-show="step === 4" x-cloak class="divide-y divide-gray-100 rounded-xl bg-white shadow-theme-xs dark:divide-gray-800 dark:bg-gray-900">
                        <div class="flex items-start gap-4 p-5">
                            <dt class="w-36 shrink-0 text-sm text-gray-500 dark:text-gray-400">Nama toko</dt>
                            <dd class="flex-1 text-sm font-medium text-gray-800 dark:text-white/90">
                                <span x-text="store_name"></span><span x-show="tagline" class="font-normal text-gray-500" x-text="' · ' + tagline"></span>
                            </dd>
                            <button type="button" class="text-sm text-brand-500 hover:underline" x-on:click="goTo(1)">Ubah</button>
                        </div>
                        <div class="flex items-start gap-4 p-5">
                            <dt class="w-36 shrink-0 text-sm text-gray-500 dark:text-gray-400">Kontak & alamat</dt>
                            <dd class="flex-1 text-sm font-medium text-gray-800 dark:text-white/90">
                                <span x-text="phone || '–'"></span><br>
                                <span class="font-normal text-gray-500" x-text="address || 'Alamat belum diisi'"></span>
                            </dd>
                            <button type="button" class="text-sm text-brand-500 hover:underline" x-on:click="goTo(1)">Ubah</button>
                        </div>
                        <div class="flex items-start gap-4 p-5">
                            <dt class="w-36 shrink-0 text-sm text-gray-500 dark:text-gray-400">Jenis usaha</dt>
                            <dd class="flex-1 text-sm font-medium text-gray-800 dark:text-white/90" x-text="selected?.label ?? '–'"></dd>
                            <button type="button" class="text-sm text-brand-500 hover:underline" x-on:click="goTo(2)">Ubah</button>
                        </div>
                        <div class="flex items-start gap-4 p-5">
                            <dt class="w-36 shrink-0 text-sm text-gray-500 dark:text-gray-400">Fitur</dt>
                            <dd class="flex flex-1 flex-wrap gap-1.5">
                                <template x-for="f in moduleFeatures" :key="f.value">
                                    <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-600 dark:bg-brand-500/15 dark:text-brand-400" x-text="f.label"></span>
                                </template>
                            </dd>
                            <button type="button" class="text-sm text-brand-500 hover:underline" x-on:click="goTo(2)">Ubah</button>
                        </div>
                    </dl>

                    @if ($errors->any())
                        <div class="mt-5 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
                            @foreach ($errors->all() as $message)
                                <p>{{ $message }}</p>
                            @endforeach
                        </div>
                    @endif
                    <p x-show="stepError" x-text="stepError" class="mt-4 text-sm text-error-500"></p>
                </div>

                {{-- Navigasi: ← Kembali (teks) + tombol pil biru --}}
                <div class="mt-10 flex items-center gap-6">
                    <button type="button" x-show="step > 1" x-on:click="step--; stepError = ''" x-bind:disabled="submitting"
                        class="flex items-center gap-1.5 text-sm font-medium text-brand-600 hover:text-brand-700 disabled:pointer-events-none disabled:opacity-40 dark:text-brand-400">
                        <x-svg.arrow-left class="size-4 rtl:rotate-180" /> Kembali
                    </button>
                    <button type="button" x-show="step < 4" x-on:click="next()"
                        class="h-11 rounded-full bg-brand-600 px-7 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-700">
                        Lanjut
                    </button>
                    <button type="submit" x-show="step === 4" x-cloak x-bind:disabled="submitting" x-bind:aria-busy="submitting"
                        class="flex h-11 items-center gap-2 rounded-full bg-brand-600 px-7 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-700 disabled:cursor-wait disabled:opacity-80">
                        {{-- Spinner saat menyimpan --}}
                        <svg x-show="submitting" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
                        </svg>
                        <span x-text="submitting ? 'Menyimpan...' : 'Mulai Gunakan'">Mulai Gunakan</span>
                    </button>
                </div>
            </main>
        </form>

        <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
        <form id="cancel-form" method="POST" action="{{ route('setup.cancel') }}" class="hidden">@csrf</form>
    </div>
@endsection

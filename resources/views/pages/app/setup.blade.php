@extends('layouts.fullscreen-layout')
@use('App\Enums\BusinessType')
@use('App\Enums\Feature')
@use('App\Http\Controllers\App\SetupController')

{{-- Wizard setup toko (dulu filament/pages/setup-wizard). Langkah dikelola Alpine; dikirim sekali saat "Mulai Gunakan". --}}
@php
    $existingLogo = $setting->logoUrl();
    $headings = [
        1 => ['Identitas Toko', 'Nama dan kontak ini tampil di aplikasi dan dicetak di struk.'],
        2 => ['Jenis Usaha', 'Usaha apa yang Anda jalankan? Fitur akan disesuaikan otomatis.'],
        3 => ['Fitur', 'Fitur sudah dicentang sesuai jenis usaha. Ubah bila perlu.'],
        4 => ['Ringkasan', 'Periksa kembali sebelum mulai menggunakan aplikasi.'],
    ];
    $businesses = collect(BusinessType::cases())->map(fn ($t) => [
        'value' => $t->value, 'label' => $t->label(), 'description' => $t->description(), 'icon' => $t->iconPath(),
        'preset' => array_map(fn ($f) => $f->value, $t->defaultFeatures()),
    ])->values();
    $features = collect(Feature::cases())->map(fn ($f) => [
        'value' => $f->value, 'label' => $f->label(), 'description' => $f->description(), 'icon' => $f->iconPath(),
        'only' => $f->businessTypes() ? array_map(fn ($b) => $b->value, $f->businessTypes()) : null,
    ])->values();
    $storeName = old('store_name', $setting->store_name === \App\Models\StoreSetting::DEFAULT_NAME ? '' : $setting->store_name);
@endphp

@section('content')
    <div class="flex min-h-screen items-center justify-center bg-gray-50 p-4 sm:p-8 dark:bg-gray-900">
        <form method="POST" action="{{ route('setup.store') }}" enctype="multipart/form-data"
            class="grid w-full max-w-5xl overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-theme-lg lg:grid-cols-[300px_1fr] dark:border-gray-800 dark:bg-gray-900"
            x-on:keydown.enter="if (step < 4 && $event.target.tagName !== 'TEXTAREA') { $event.preventDefault(); next(); }"
            x-data="{
                step: {{ $errors->any() ? $errorStep : 1 }},
                reached: {{ $errors->any() ? 4 : 1 }},
                store_name: @js($storeName),
                tagline: @js(old('tagline', $setting->tagline)),
                phone: @js(old('phone', $setting->phone)),
                address: @js(old('address', $setting->address)),
                logoPreview: @js($existingLogo),
                businessType: @js(old('business_type')),
                features: @js(old('features', [])),
                businesses: @js($businesses),
                featureList: @js($features),
                stepError: '',
                get selected() { return this.businesses.find((b) => b.value === this.businessType) ?? null; },
                get availableFeatures() {
                    return this.featureList.filter((f) => !f.only || (this.businessType && f.only.includes(this.businessType)));
                },
                pick(type) {
                    if (this.businessType === type) return;
                    this.businessType = type;
                    this.features = [...(this.businesses.find((b) => b.value === type)?.preset ?? [])];
                    this.stepError = '';
                },
                toggle(feature) {
                    this.features = this.features.includes(feature) ? this.features.filter((f) => f !== feature) : [...this.features, feature];
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
            <template x-for="feature in features.filter((f) => availableFeatures.some((a) => a.value === f))" :key="feature">
                <input type="hidden" name="features[]" x-bind:value="feature">
            </template>

            {{-- ===== Kiri: pelacak langkah ===== --}}
            <aside class="flex flex-col bg-brand-950 p-8 text-white dark:bg-white/3">
                <div class="mb-8 flex h-11 items-center">
                    @if ($existingLogo)
                        <img src="{{ $existingLogo }}" alt="" class="max-h-11 rounded-lg bg-white object-contain p-1" />
                    @else
                        <span class="flex items-center gap-2 text-lg font-semibold">
                            <span class="flex size-9 items-center justify-center rounded-lg bg-brand-500"><x-svg.gear class="size-5" /></span>
                            Setup
                        </span>
                    @endif
                </div>
                <h2 class="text-xl font-semibold">Setup Toko</h2>
                <p class="mt-1 text-sm text-white/60">Siapkan toko Anda dalam beberapa langkah.</p>

                <ol class="mt-8 space-y-2">
                    @foreach (SetupController::STEPS as $number => $label)
                        <li>
                            <button type="button" x-on:click="goTo({{ $number }})" x-bind:disabled="{{ $number }} > reached"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-start text-sm transition disabled:cursor-default"
                                x-bind:class="step === {{ $number }} ? 'bg-white/10 font-semibold text-white' : ({{ $number }} < step ? 'text-white/80 hover:bg-white/5' : 'text-white/40')">
                                <span class="flex size-7 shrink-0 items-center justify-center rounded-full border text-xs"
                                    x-bind:class="{{ $number }} < step ? 'border-success-500 bg-success-500 text-white' : (step === {{ $number }} ? 'border-white text-white' : 'border-white/30')">
                                    <x-svg.check class="size-3.5" x-show="{{ $number }} < step" />
                                    <span x-show="{{ $number }} >= step">{{ $number }}</span>
                                </span>
                                {{ $label }}
                            </button>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-auto pt-10 text-xs text-white/50">
                    <p>Semua pengaturan bisa diubah lagi di menu Pengaturan Toko.</p>
                    <p class="mt-1">&copy; {{ now()->year }}</p>
                </div>
            </aside>

            {{-- ===== Kanan: isi langkah ===== --}}
            <main class="relative flex min-h-[560px] flex-col p-6 sm:p-10">
                <button type="submit" form="logout-form" class="absolute end-6 top-6 flex items-center gap-1.5 text-sm text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" title="Keluar dari akun">
                    <x-svg.log-out class="size-4" /> Keluar
                </button>

                @foreach ($headings as $number => [$heading, $sub])
                    <div x-show="step === {{ $number }}" @if ($number !== 1) x-cloak @endif>
                        <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">{{ $heading }}</h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $sub }}</p>
                    </div>
                @endforeach

                <div class="mt-8 flex-1">
                    {{-- 1. Identitas toko --}}
                    <div x-show="step === 1" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
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
                                <span class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-white/3">
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

                    {{-- 2. Jenis usaha --}}
                    <div x-show="step === 2" x-cloak class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <template x-for="b in businesses" :key="b.value">
                            <button type="button" x-on:click="pick(b.value)"
                                class="flex items-start gap-3 rounded-2xl border-2 p-4 text-start transition"
                                x-bind:class="businessType === b.value ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700'">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand-500 shadow-theme-xs dark:bg-gray-900">
                                    <svg class="size-5 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" x-bind:d="b.icon" /></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-white/90" x-text="b.label"></span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="b.description"></span>
                                </span>
                                <span class="flex size-5 shrink-0 items-center justify-center rounded-full border"
                                    x-bind:class="businessType === b.value ? 'border-brand-500 bg-brand-500 text-white' : 'border-gray-300 dark:border-gray-700'">
                                    <x-svg.check class="size-3" x-show="businessType === b.value" />
                                </span>
                            </button>
                        </template>
                    </div>

                    {{-- 3. Fitur --}}
                    <div x-show="step === 3" x-cloak>
                        <p x-show="selected" class="mb-4 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                            Dicentang otomatis untuk <strong x-text="selected?.label"></strong>. Mematikan fitur hanya menyembunyikannya; data tidak dihapus.
                        </p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <template x-for="f in availableFeatures" :key="f.value">
                                <button type="button" x-on:click="toggle(f.value)" x-bind:aria-pressed="features.includes(f.value)"
                                    class="flex items-start gap-3 rounded-2xl border-2 p-4 text-start transition"
                                    x-bind:class="features.includes(f.value) ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : 'border-gray-200 hover:border-gray-300 dark:border-gray-800 dark:hover:border-gray-700'">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-white text-brand-500 shadow-theme-xs dark:bg-gray-900">
                                        <svg class="size-5 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" x-bind:d="f.icon" /></svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-gray-800 dark:text-white/90" x-text="f.label"></span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="f.description"></span>
                                    </span>
                                    <span class="flex size-5 shrink-0 items-center justify-center rounded-md border"
                                        x-bind:class="features.includes(f.value) ? 'border-brand-500 bg-brand-500 text-white' : 'border-gray-300 dark:border-gray-700'">
                                        <x-svg.check class="size-3" x-show="features.includes(f.value)" />
                                    </span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- 4. Ringkasan --}}
                    <dl x-show="step === 4" x-cloak class="divide-y divide-gray-100 rounded-2xl border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                        <div class="flex items-start gap-4 p-4">
                            <dt class="w-36 shrink-0 text-sm text-gray-500 dark:text-gray-400">Nama toko</dt>
                            <dd class="flex-1 text-sm font-medium text-gray-800 dark:text-white/90">
                                <span x-text="store_name"></span><span x-show="tagline" class="font-normal text-gray-500" x-text="' · ' + tagline"></span>
                            </dd>
                            <button type="button" class="text-sm text-brand-500 hover:underline" x-on:click="goTo(1)">Ubah</button>
                        </div>
                        <div class="flex items-start gap-4 p-4">
                            <dt class="w-36 shrink-0 text-sm text-gray-500 dark:text-gray-400">Kontak & alamat</dt>
                            <dd class="flex-1 text-sm font-medium text-gray-800 dark:text-white/90">
                                <span x-text="phone || '–'"></span><br>
                                <span class="font-normal text-gray-500" x-text="address || 'Alamat belum diisi'"></span>
                            </dd>
                            <button type="button" class="text-sm text-brand-500 hover:underline" x-on:click="goTo(1)">Ubah</button>
                        </div>
                        <div class="flex items-start gap-4 p-4">
                            <dt class="w-36 shrink-0 text-sm text-gray-500 dark:text-gray-400">Jenis usaha</dt>
                            <dd class="flex-1 text-sm font-medium text-gray-800 dark:text-white/90" x-text="selected?.label ?? '–'"></dd>
                            <button type="button" class="text-sm text-brand-500 hover:underline" x-on:click="goTo(2)">Ubah</button>
                        </div>
                        <div class="flex items-start gap-4 p-4">
                            <dt class="w-36 shrink-0 text-sm text-gray-500 dark:text-gray-400">Fitur</dt>
                            <dd class="flex flex-1 flex-wrap gap-1.5">
                                <template x-for="f in availableFeatures" :key="f.value">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        x-bind:class="features.includes(f.value) ? 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400' : 'bg-gray-100 text-gray-400 line-through dark:bg-white/5'"
                                        x-text="f.label"></span>
                                </template>
                            </dd>
                            <button type="button" class="text-sm text-brand-500 hover:underline" x-on:click="goTo(3)">Ubah</button>
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

                <div class="mt-8 flex items-center justify-between gap-3">
                    <x-ui.button variant="outline" x-show="step > 1" x-on:click="step--; stepError = ''">
                        <x-svg.arrow-left class="size-4 rtl:rotate-180" /> Kembali
                    </x-ui.button>
                    <span x-show="step === 1"></span>
                    <x-ui.button x-show="step < 4" x-on:click="next()">Lanjut</x-ui.button>
                    <x-ui.button type="submit" x-show="step === 4" x-cloak>Mulai Gunakan</x-ui.button>
                </div>
            </main>
        </form>

        <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
    </div>
@endsection

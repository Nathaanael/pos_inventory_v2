@extends('layouts.fullscreen-layout')

@php
    $store = \App\Models\StoreSetting::current();
    $storeName = \App\Models\StoreSetting::storeName();
@endphp

@section('content')
    <div class="relative z-1 bg-white p-6 sm:p-0 dark:bg-gray-900">
        <div class="relative flex h-screen w-full flex-col justify-center sm:p-0 lg:flex-row dark:bg-gray-900">
            <!-- Panel kiri: carousel jenis usaha + merek toko -->
            <div class="bg-brand-950 relative hidden h-full w-full overflow-hidden lg:block lg:w-1/2 dark:bg-gray-950">
                <x-main.auth.business-marquee class="px-10 xl:px-16" />

                {{-- Fade atas/bawah agar kartu muncul & hilang halus. --}}
                <div class="from-brand-950 pointer-events-none absolute inset-x-0 top-0 z-1 h-40 bg-linear-to-b to-transparent dark:from-gray-950"></div>
                <div class="from-brand-950 via-brand-950/90 pointer-events-none absolute inset-x-0 bottom-0 z-1 h-72 bg-linear-to-t to-transparent dark:from-gray-950 dark:via-gray-950/90"></div>

                {{-- Logo toko ada di atas judul "Masuk" (kolom form); panel ini hanya tagline. --}}
                <div class="absolute inset-x-0 bottom-0 z-9 flex flex-col items-center px-10 pb-12 text-center">
                    <!-- <p class="max-w-sm text-gray-300 dark:text-white/60">Aplikasi kasir & manajemen inventaris untuk berbagai jenis usaha</p> -->
                </div>
            </div>

            <!-- Form -->
            <div class="flex w-full flex-1 flex-col lg:w-1/2">
                <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center">
                    {{-- Logo toko di atas "Masuk" (semua ukuran layar). Tanpa logo: merek/nama toko. --}}
                    <div class="mb-8">
                        @if ($store->logoUrl())
                            {{-- Latar putih di mode gelap agar logo berwarna gelap tetap terbaca. --}}
                            <img src="{{ $store->logoUrl() }}" alt="{{ $storeName }}"
                                class="h-auto max-h-14 w-auto max-w-56 object-contain dark:rounded-xl dark:bg-white dark:p-2" />
                        @else
                            <x-common.store-brand href="#" :collapsible="false" />
                        @endif
                    </div>

                    <div class="mb-5 sm:mb-8">
                        <h1 class="text-title-sm sm:text-title-md mb-2 font-semibold text-gray-800 dark:text-white/90">Masuk</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Masukkan email dan password untuk masuk ke aplikasi.</p>
                    </div>

                    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                        @csrf

                        <x-common.form.field label="Email" for="email" required>
                            <x-ui.input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="nama@toko.com"
                                autocomplete="username" autofocus required :aria-invalid="$errors->has('email') ? 'true' : null" />
                        </x-common.form.field>

                        <x-common.form.field label="Password" for="password" required>
                            <div x-data="{ showPassword: false }" class="relative">
                                <x-ui.input x-bind:type="showPassword ? 'text' : 'password'" type="password" id="password" name="password"
                                    placeholder="Masukkan password" autocomplete="current-password" required class="pe-11" />
                                <button type="button" x-on:click="showPassword = !showPassword"
                                    x-bind:aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                    class="absolute end-4 top-1/2 z-30 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                    <x-svg.eye class="size-5" x-show="!showPassword" />
                                    <x-svg.eye-close class="size-5" x-show="showPassword" x-cloak />
                                </button>
                            </div>
                        </x-common.form.field>

                        <label class="flex w-fit cursor-pointer items-center gap-3 text-sm font-normal text-gray-700 select-none dark:text-gray-400">
                            <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                                class="size-5 rounded-md border-gray-300 text-brand-500 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900" />
                            Ingat saya
                        </label>

                        <x-ui.button type="submit" class="w-full" size="lg">Masuk</x-ui.button>
                    </form>
                </div>
            </div>

            <!-- Toggler tema -->
            <div class="fixed end-6 bottom-6 z-50">
                <x-ui.button size="icon-lg" class="size-14 rounded-full" aria-label="Ganti tema" x-on:click.prevent="$store.theme.toggle()">
                    <x-svg.sun class="size-5" x-show="$store.theme.resolvedTheme === 'dark'" />
                    <x-svg.moon class="size-5" x-show="$store.theme.resolvedTheme !== 'dark'" />
                </x-ui.button>
            </div>
        </div>
    </div>
@endsection

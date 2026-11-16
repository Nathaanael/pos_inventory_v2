@extends('layouts.fullscreen-layout')

@php
    $store = \App\Models\StoreSetting::current();
    $storeName = \App\Models\StoreSetting::storeName();
@endphp

@section('content')
    <div class="relative z-1 bg-white p-6 sm:p-0 dark:bg-gray-900">
        <div class="relative flex h-screen w-full flex-col justify-center sm:p-0 lg:flex-row dark:bg-gray-900">
            <!-- Form -->
            <div class="flex w-full flex-1 flex-col lg:w-1/2">
                <div class="mx-auto flex w-full max-w-md flex-1 flex-col justify-center">
                    <div class="mb-8 lg:hidden">
                        <x-common.store-brand href="#" :collapsible="false" />
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

            <!-- Panel merek toko -->
            <div class="bg-brand-950 relative hidden h-full w-full items-center lg:grid lg:w-1/2 dark:bg-white/5">
                <div class="z-1 flex items-center justify-center">
                    <x-common.common-grid-shape />
                    <div class="flex max-w-xs flex-col items-center">
                        {{-- Ada logo: tampilkan logo saja; tanpa logo: nama toko. --}}
                        @if ($store->logoUrl())
                            <img src="{{ $store->logoUrl() }}" alt="{{ $storeName }}" class="mb-4 max-h-28 w-auto max-w-60 rounded-2xl bg-white object-contain p-3" />
                        @else
                            <h2 class="mb-2 text-center text-2xl font-semibold text-white">{{ $storeName }}</h2>
                        @endif
                        <p class="text-center text-gray-400 dark:text-white/60">Aplikasi kasir & manajemen inventaris</p>
                    </div>
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

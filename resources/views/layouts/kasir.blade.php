<!DOCTYPE html>
{{-- Layout halaman kasir (dulu panel Filament "kasir" dengan ->topNavigation()): header + navigasi atas, tanpa sidebar. --}}
@php
    $storeName = \App\Models\StoreSetting::storeName();
    // Link navigasi atas: lebar mengikuti isi (bukan w-full seperti menu sidebar).
    $navLink = 'inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors';
    $navActive = 'bg-brand-50 text-brand-500 dark:bg-brand-500/[0.12] dark:text-brand-400';
    $navInactive = 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5';
    $nav = array_values(array_filter([
        ['label' => 'Kasir', 'route' => 'kasir.pos', 'icon' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z'],
        \App\Models\StoreSetting::hasFeature(\App\Enums\Feature::DiningTables)
            ? ['label' => 'Meja', 'route' => 'kasir.meja', 'icon' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z']
            : null,
        ['label' => 'Riwayat Transaksi', 'route' => 'kasir.riwayat', 'icon' => 'M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm4.125 4.5h.008v.008h-.008V13.5Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z'],
        \App\Models\StoreSetting::usesModifiers()
            ? ['label' => 'Dapur (KDS)', 'route' => 'kasir.kds.index', 'icon' => 'M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z']
            : null,
        ['label' => 'Member', 'route' => 'kasir.members.index', 'icon' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z'],
        ['label' => 'Shift Kasir', 'route' => 'kasir.shift.index', 'icon' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
    ]));
@endphp
<html lang="id" dir="ltr" class="h-full bg-gray-50 dark:bg-gray-900">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('layouts.head-meta')

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    @include('layouts.theme-store')
</head>

<body class="min-h-screen bg-gray-50 dark:bg-gray-900">
    <header class="sticky top-0 z-50 border-b border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900" id="kasir-header">
        <div class="flex items-center gap-4 px-4 py-3 md:px-6">
            <x-common.store-brand :href="route('kasir.pos')" :collapsible="false" suffix="· Kasir" />

            <nav class="no-scrollbar ms-2 hidden flex-1 items-center gap-1 overflow-x-auto md:flex">
                @foreach ($nav as $item)
                    @php $active = request()->routeIs($item['route']); @endphp
                    {{-- Jangan pakai utilitas menu-item: itu untuk sidebar (w-full) dan membuat link pertama memenuhi baris. --}}
                    <a href="{{ route($item['route']) }}" @class([$navLink, $navActive => $active, $navInactive => ! $active])>
                        <svg class="size-5 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" /></svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="ms-auto flex items-center gap-2 sm:gap-3">
                @if (auth()->user()?->isAdmin())
                    <x-ui.button as="a" href="{{ url('/') }}" variant="outline" class="hidden sm:inline-flex">
                        <x-svg.arrow-left class="size-4 rtl:rotate-180" /> Back Office
                    </x-ui.button>
                @endif
                <x-ui.button variant="outline" size="icon" aria-label="Ganti tema" x-data x-on:click="$store.theme.toggle()">
                    <x-svg.sun x-show="$store.theme.resolvedTheme === 'dark'" />
                    <x-svg.moon x-show="$store.theme.resolvedTheme !== 'dark'" />
                </x-ui.button>
                <x-header.user-dropdown />
            </div>
        </div>

        {{-- Navigasi mobile --}}
        <nav class="no-scrollbar flex gap-1 overflow-x-auto border-t border-gray-100 px-3 py-2 md:hidden dark:border-gray-800">
            @foreach ($nav as $item)
                @php $active = request()->routeIs($item['route']); @endphp
                <a href="{{ route($item['route']) }}" @class([$navLink, $navActive => $active, $navInactive => ! $active])>{{ $item['label'] }}</a>
            @endforeach
            @if (auth()->user()?->isAdmin())
                <a href="{{ url('/') }}" class="{{ $navLink }} {{ $navInactive }}">Back Office</a>
            @endif
        </nav>
    </header>

    <main class="p-4 md:p-6">
        @yield('content')
    </main>

    <x-common.loading-bar />
    <x-common.flash />
    <x-common.confirm-dialog />
</body>

@stack('scripts')

</html>

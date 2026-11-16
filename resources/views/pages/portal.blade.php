@extends('layouts.fullscreen-layout')

{{-- Pemilih modul aplikasi (dulu: panel Filament "portal" → AppLauncher). --}}
@php
    $store = \App\Models\StoreSetting::current();
    $storeName = \App\Models\StoreSetting::storeName();
    $apps = [
        [
            'url' => url('/'),
            'label' => 'Back Office',
            'sub' => 'Manajemen barang, gudang & laporan',
            'class' => 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400',
            'icon' => 'M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75',
        ],
        [
            'url' => url('/kasir'),
            'label' => 'Kasir',
            'sub' => 'Penjualan & cetak nota',
            'class' => 'bg-error-50 text-error-500 dark:bg-error-500/15 dark:text-error-400',
            'icon' => 'M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z',
        ],
    ];
@endphp

@section('content')
    <div class="relative flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-10 dark:bg-gray-900">
        <div class="mb-1.5 flex items-center gap-3.5">
            @if ($store->logoUrl())
                <img src="{{ $store->logoUrl() }}" alt="{{ $storeName }}" class="h-11 w-11 rounded-xl object-contain" />
            @else
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
                    <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72" />
                    </svg>
                </span>
            @endif
            <h1 class="text-title-sm font-semibold text-gray-800 dark:text-white/90">{{ $storeName }}</h1>
        </div>

        <div class="mx-auto my-5 h-0.5 w-10 rounded-full bg-gray-200 dark:bg-white/10"></div>
        <p class="mb-8 text-center text-xs tracking-widest text-gray-400 uppercase">Pilih modul aplikasi</p>

        <div class="grid w-full max-w-xl grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ($apps as $app)
                <a href="{{ $app['url'] }}"
                    class="group flex flex-col items-center rounded-2xl border border-gray-200 bg-white px-6 pt-8 pb-7 text-center transition hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-theme-md dark:border-gray-800 dark:bg-white/3 dark:hover:border-gray-700">
                    <span class="mb-4 flex h-13 w-13 items-center justify-center rounded-xl {{ $app['class'] }}">
                        <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $app['icon'] }}" />
                        </svg>
                    </span>
                    <span class="mb-1 text-base font-semibold text-gray-800 dark:text-white/90">{{ $app['label'] }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $app['sub'] }}</span>
                    <span class="mt-5 flex items-center gap-1 text-xs text-gray-400 transition-colors group-hover:text-brand-500">
                        Buka <x-svg.arrow-right class="size-3.5 rtl:rotate-180" />
                    </span>
                </a>
            @endforeach
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-10">
            @csrf
            <button type="submit" class="flex items-center gap-1.5 text-sm text-gray-400 transition-colors hover:text-gray-600 dark:hover:text-gray-300">
                <x-svg.log-out class="size-4" />
                Keluar
            </button>
        </form>
    </div>
@endsection

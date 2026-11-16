{{--
    Merek toko di sidebar/header. Bila toko punya logo, hanya logo yang tampil;
    bila belum, tampil ikon toko + nama toko.
    Props: href, collapsible (sembunyikan nama/logo lebar saat sidebar diciutkan), suffix (teks setelah nama, mis. "· Kasir").
--}}
@props(['href' => '/', 'collapsible' => true, 'suffix' => null])
@php
    $logo = \App\Models\StoreSetting::current()->logoUrl();
    $name = \App\Models\StoreSetting::storeName();
@endphp
<a href="{{ $href }}" title="{{ $name }}" {{ $attributes->merge(['class' => 'flex min-w-0 items-center gap-3']) }}>
    @if ($logo)
        {{-- Sidebar diciutkan: logo kecil persegi; selain itu logo penuh. --}}
        <img src="{{ $logo }}" alt="{{ $name }}" @class([
            'h-10 w-auto max-w-44 object-contain',
            'hidden [.sidebar-expanded_&]:block' => $collapsible,
        ]) />
        @if ($collapsible)
            <img src="{{ $logo }}" alt="{{ $name }}" class="block size-10 object-contain [.sidebar-expanded_&]:hidden" />
        @endif
        @if ($suffix)
            <span class="text-base font-normal whitespace-nowrap text-gray-400">{{ $suffix }}</span>
        @endif
    @else
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-500 text-white">
            <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72" />
            </svg>
        </span>
        <span @class([
            'truncate text-lg font-semibold text-gray-800 dark:text-white/90',
            'hidden [.sidebar-expanded_&]:block' => $collapsible,
        ])>{{ $name }}@if ($suffix) <span class="font-normal text-gray-400">{{ $suffix }}</span>@endif</span>
    @endif
</a>

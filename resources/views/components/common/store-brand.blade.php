{{--
    Logo + nama toko (pengganti logo TailAdmin). Mengikuti brandName/brandLogo panel Filament lama.
    Props: href, collapsible (sembunyikan nama saat sidebar diciutkan).
--}}
@props(['href' => '/', 'collapsible' => true])
@php
    $store = \App\Models\StoreSetting::current();
    $logo = $store->logoUrl();
    $name = \App\Models\StoreSetting::storeName();
@endphp
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'flex min-w-0 items-center gap-3']) }}>
    @if ($logo)
        <img src="{{ $logo }}" alt="{{ $name }}" class="h-10 w-10 shrink-0 rounded-lg object-contain" />
    @else
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-500 text-white">
            <svg class="h-6 w-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72" />
            </svg>
        </span>
    @endif
    <span @class([
        'truncate text-lg font-semibold text-gray-800 dark:text-white/90',
        'hidden [.sidebar-expanded_&]:block' => $collapsible,
    ])>{{ $name }}</span>
</a>

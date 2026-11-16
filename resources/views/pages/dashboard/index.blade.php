@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Dashboard" />

    {{-- Widget dashboard (statistik penjualan, grafik, stok terendah, barang terlaris) dikonversi pada tahap Dashboard & Laporan. --}}
    <x-common.component-card title="Selamat datang, {{ auth()->user()->name }}"
        desc="Widget dashboard (penjualan, laba rugi, grafik, stok terendah, barang terlaris) akan tersedia setelah tahap Dashboard & Laporan selesai dikonversi.">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ([['Master Barang', '/items'], ['Kategori', '/categories'], ['Supplier', '/suppliers']] as [$label, $url])
                <a href="{{ $url }}"
                    class="flex items-center justify-between rounded-xl border border-gray-200 px-5 py-4 text-sm font-medium text-gray-700 transition-colors hover:border-brand-300 hover:text-brand-500 dark:border-gray-800 dark:text-gray-300 dark:hover:border-brand-500/40 dark:hover:text-brand-400">
                    {{ $label }}
                    <x-svg.arrow-right class="size-4 rtl:rotate-180" />
                </a>
            @endforeach
        </div>
    </x-common.component-card>
@endsection

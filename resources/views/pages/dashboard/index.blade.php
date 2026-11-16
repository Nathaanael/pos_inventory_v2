@extends('layouts.app')
@use('App\Support\Format')
@use('App\Services\DashboardStats')

{{-- Dashboard admin (dulu Filament Dashboard + widget Stats, Grafik, Barang Terlaris, Stok Menipis, Laba Rugi). --}}
@php
    $trendIcon = fn ($trend) => $trend >= 0 ? 'arrow-up' : 'arrow-down';
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Dashboard">
        <form method="GET" class="flex items-center gap-2">
            <label for="period" class="text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">Filter Periode Data</label>
            <x-common.form.select id="period" name="period" class="h-10 w-48" :options="DashboardStats::PERIODS" :value="$stats->period()"
                x-data x-on:change="$el.form.requestSubmit()" />
        </form>
    </x-common.page-breadcrumb>

    <div class="space-y-6">
        {{-- Statistik hari ini --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6">
            @foreach ([
                ['label' => 'Penjualan Hari Ini', 'value' => Format::rupiah($overview['sales_today']), 'trend' => $overview['sales_trend']],
                ['label' => 'Laba Kotor Hari Ini', 'value' => Format::rupiah($overview['profit_today']), 'trend' => $overview['profit_trend']],
            ] as $card)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $card['label'] }}</span>
                    <div class="mt-2 flex items-end justify-between gap-3">
                        <h4 class="text-title-sm font-bold text-gray-800 dark:text-white/90">{{ $card['value'] }}</h4>
                        <x-ui.badge :color="$card['trend'] >= 0 ? 'success' : 'error'" class="gap-1">
                            <x-dynamic-component :component="'svg.' . $trendIcon($card['trend'])" class="size-3.5" />
                            {{ abs(round($card['trend'], 1)) }}%
                        </x-ui.badge>
                    </div>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">dari kemarin</p>
                </div>
            @endforeach

            <x-common.stat-card label="Barang Masuk Hari Ini" :value="Format::rupiah($overview['inbound_today'])" description="Barang diterima hari ini" color="info"
                icon="M7.5 7.5h-.75A2.25 2.25 0 0 0 4.5 9.75v7.5a2.25 2.25 0 0 0 2.25 2.25h7.5a2.25 2.25 0 0 0 2.25-2.25v-7.5a2.25 2.25 0 0 0-2.25-2.25h-.75m-6 3.75 3 3m0 0 3-3m-3 3V1.5m6 9h.75a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25h-7.5a2.25 2.25 0 0 1-2.25-2.25v-.75" />
            <x-common.stat-card label="Stok Menipis" :value="$overview['low_stock'].' Barang'"
                :description="$overview['low_stock'] > 0 ? 'Perlu restock segera' : 'Stok aman'" :color="$overview['low_stock'] > 0 ? 'error' : 'success'"
                icon="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </div>

        {{-- Trend penjualan & laba --}}
        <x-common.component-card title="Trend Penjualan & Laba" :desc="$stats->periodLabel()">
            <div class="custom-scrollbar -mx-2 overflow-x-auto">
                <div class="min-w-[640px]" data-dashboard-chart="sales-profit" data-config="{{ json_encode($salesChart) }}"></div>
            </div>
        </x-common.component-card>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            {{-- Penjualan per kategori --}}
            <x-common.component-card title="Penjualan per Kategori" :desc="$stats->periodLabel()">
                @if (count($categoryChart['totals']))
                    <div data-dashboard-chart="sales-by-category" data-config="{{ json_encode($categoryChart) }}"></div>
                @else
                    <p class="py-16 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada penjualan pada periode ini.</p>
                @endif
            </x-common.component-card>

            {{-- Barang stok menipis --}}
            <x-common.table-card title="Barang Stok Menipis" :search="false" :paginator="$lowStock">
                <thead class="border-y border-gray-100 dark:border-gray-800">
                    <tr>
                        <th class="table-th">Nama Barang</th>
                        <th class="table-th">Stok</th>
                        <th class="table-th">Min</th>
                        <th class="table-th">Kurang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($lowStock as $item)
                        <tr class="table-row">
                            <td class="table-td font-medium text-gray-800 dark:text-white/90">{{ $item->name }}</td>
                            <td class="table-td"><x-ui.badge size="sm" :color="$item->current_stock <= 0 ? 'error' : 'warning'">{{ Format::number($item->current_stock) }}</x-ui.badge></td>
                            <td class="table-td">{{ Format::number($item->min_stock) }}</td>
                            <td class="table-td"><x-ui.badge size="sm" color="error">{{ Format::number(max(0, $item->min_stock - $item->current_stock)) }}</x-ui.badge></td>
                        </tr>
                    @empty
                        <x-common.table-empty colspan="4" title="Stok aman" description="Tidak ada barang di bawah stok minimum." />
                    @endforelse
                </tbody>
            </x-common.table-card>
        </div>

        {{-- Top 10 barang terlaris --}}
        <x-common.table-card title="Top 10 Barang Terlaris" :description="$stats->periodLabel()" :search="false">
            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th">Nama Barang</th>
                    <th class="table-th">Kuantitas</th>
                    <th class="table-th text-end">Pendapatan</th>
                    <th class="table-th text-end">Laba</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($topSelling as $row)
                    <tr class="table-row">
                        <td class="table-td font-medium text-gray-800 dark:text-white/90">{{ $row->item_name }}</td>
                        <td class="table-td"><x-ui.badge size="sm" color="success">{{ Format::number($row->total_qty) }}</x-ui.badge></td>
                        <td class="table-td text-end whitespace-nowrap">{{ Format::rupiah($row->total_revenue) }}</td>
                        <td class="table-td text-end whitespace-nowrap">{{ Format::rupiah($row->total_profit) }}</td>
                    </tr>
                @empty
                    <x-common.table-empty colspan="4" title="Belum ada penjualan" description="Belum ada barang terjual pada periode ini." />
                @endforelse
            </tbody>
        </x-common.table-card>

        {{-- Laporan laba rugi --}}
        <x-common.component-card title="Laporan Laba Rugi" :desc="'Data berdasarkan filter: '.$stats->periodLabel()">
            <dl class="divide-y divide-gray-100 dark:divide-gray-800">
                <div class="flex items-center justify-between py-3">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Pendapatan (Penjualan)</dt>
                    <dd class="font-medium text-gray-800 dark:text-white/90">{{ Format::rupiah($profitLoss['revenue']) }}</dd>
                </div>
                <div class="flex items-center justify-between py-3">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">HPP (Modal Terjual)</dt>
                    <dd class="font-medium text-error-500">- {{ Format::rupiah($profitLoss['cogs']) }}</dd>
                </div>
                <div class="flex items-center justify-between py-3">
                    <dt class="text-sm font-semibold text-gray-800 dark:text-white/90">Laba Kotor</dt>
                    <dd class="font-semibold text-brand-500 dark:text-brand-400">{{ Format::rupiah($profitLoss['gross_profit']) }}</dd>
                </div>
                <div class="flex items-center justify-between py-3">
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Retur Pembelian/Penjualan</dt>
                    <dd class="font-medium text-warning-600 dark:text-orange-400">- {{ Format::rupiah($profitLoss['returns']) }}</dd>
                </div>
            </dl>
            <div class="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/3">
                <span class="font-bold text-gray-800 dark:text-white/90">Laba Bersih (Estimasi)</span>
                <span @class(['text-lg font-bold', 'text-success-600 dark:text-success-500' => $profitLoss['net_profit'] >= 0, 'text-error-500' => $profitLoss['net_profit'] < 0])>
                    {{ Format::rupiah($profitLoss['net_profit']) }}
                </span>
            </div>
        </x-common.component-card>
    </div>
@endsection

@extends('layouts.app')
@use('Illuminate\Support\Js')

@php
    $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');

    // Badge "Aktivitas Terakhir" (hanya pergerakan dalam 24 jam terakhir), sama seperti versi Filament.
    $activity = function ($item) {
        $movement = $item->latestMovement;
        if (! $movement || now()->diffInHours($movement->created_at, true) > 24) {
            return null;
        }

        $label = match ($movement->type) {
            'inbound' => 'Inbound',
            'outbound' => 'Outbound',
            'return_in' => 'Return In',
            'return_out' => 'Retur',
            'adjustment' => 'Adjustment',
            default => ucfirst($movement->type),
        };
        $color = match ($movement->type) {
            'inbound', 'return_in' => 'success',
            'outbound' => 'warning',
            'return_out' => 'error',
            'adjustment' => 'info',
            default => 'gray',
        };

        return ['label' => "{$label} ({$movement->quantity})", 'color' => $color];
    };
@endphp

@section('content')
    <x-common.page-breadcrumb pageTitle="Master Barang" />

    {{-- Widget statistik (ItemStatsWidget) --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6">
        <x-common.stat-card label="Total Jenis Barang" :value="number_format($stats['total'], 0, ',', '.')" description="Seluruh item terdaftar" color="brand"
            icon="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
        <x-common.stat-card label="Estimasi Nilai Aset" :value="$rupiah($stats['asset'])" description="Berdasarkan harga beli gudang" color="success"
            icon="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
        <x-common.stat-card label="Stok Menipis / Habis" :value="number_format($stats['low_stock'], 0, ',', '.')" description="Perlu segera restock"
            :color="$stats['low_stock'] > 0 ? 'error' : 'success'"
            icon="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        <x-common.stat-card label="Pergerakan Hari Ini" :value="number_format($stats['movements_today'], 0, ',', '.')" description="Mutasi stok (Barang Masuk/Keluar/dll)" color="info"
            icon="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
    </div>

    <div x-data="{
            historyOpen: false,
            detailOpen: false,
            panelTitle: '',
            panelHtml: '',
            loading: false,
            async load(url, panel, title) {
                this.panelTitle = title;
                this.panelHtml = '';
                this.loading = true;
                this[panel] = true;
                try {
                    const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    this.panelHtml = response.ok ? await response.text() : '<p class=&quot;text-sm text-error-500&quot;>Gagal memuat data.</p>';
                } finally {
                    this.loading = false;
                }
            },
        }">
        <x-common.table-card title="Daftar Master Barang" :paginator="$items">
            <x-slot:filters>
                @if ($hasBrand)
                    <x-common.form.select name="brand_id" class="h-10 sm:w-40" :value="request('brand_id')" placeholder="Semua merk"
                        :options="$brandOptions" x-data x-on:change="$el.form.requestSubmit()" aria-label="Filter merk" />
                @endif
                @if ($usesSuppliers)
                    <x-common.form.select name="supplier" class="h-10 sm:w-44" :value="request('supplier')" placeholder="Semua supplier"
                        :options="$supplierOptions" x-data x-on:change="$el.form.requestSubmit()" aria-label="Filter supplier" />
                @endif
                <x-common.form.select name="is_active" class="h-10 sm:w-36" :value="request('is_active')" placeholder="Semua status"
                    :options="['1' => 'Aktif', '0' => 'Tidak Aktif']" x-data x-on:change="$el.form.requestSubmit()" aria-label="Filter status aktif" />
            </x-slot:filters>

            <x-slot:actions>
                <x-ui.button as="a" href="{{ route('items.create') }}">
                    <x-svg.plus class="size-5" />
                    Tambah Barang
                </x-ui.button>
            </x-slot:actions>

            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th min-w-28">Kode</th>
                    <th class="table-th min-w-32">Barcode</th>
                    <th class="table-th min-w-52">Nama</th>
                    @if ($usesSuppliers)
                        <th class="table-th min-w-40">Supplier</th>
                    @endif
                    <th class="table-th min-w-32">Harga Beli</th>
                    <th class="table-th min-w-28">Stok Saat Ini</th>
                    <th class="table-th min-w-40">Aktivitas Terakhir</th>
                    <th class="table-th min-w-20">Aktif</th>
                    <th class="table-th w-16"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($items as $item)
                    @php
                        $suppliers = $item->suppliers;
                        $supplierCount = $item->itemSuppliers->count();
                        $badge = $activity($item);
                        $detailCall = 'load('.Js::from(route('items.show', $item)).", 'detailOpen', ".Js::from($item->name).')';
                    @endphp
                    <tr class="table-row cursor-pointer" x-on:click="if (! $event.target.closest('button, a, [role=menu]')) {{ $detailCall }}">
                        <td class="table-td whitespace-nowrap">{{ $item->code }}</td>
                        <td class="table-td whitespace-nowrap">{{ $item->barcode ?: '-' }}</td>
                        <td class="table-td font-medium text-gray-800 dark:text-white/90">{{ $item->name }}</td>
                        @if ($usesSuppliers)
                        <td class="table-td">
                            @if ($suppliers->isNotEmpty())
                                <x-ui.badge size="sm" color="primary" class="whitespace-nowrap">
                                    {{ $suppliers->first()->name }}{{ $suppliers->count() > 1 ? ' ... +' : '' }}
                                </x-ui.badge>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        @endif
                        <td class="table-td whitespace-nowrap">
                            <span class="text-gray-800 dark:text-white/90">{{ $rupiah($item->harga_beli) }}</span>
                            @if ($usesSuppliers && $supplierCount > 1)
                                <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $supplierCount }} supplier</span>
                            @endif
                        </td>
                        <td class="table-td whitespace-nowrap">
                            <span @class(['font-medium', 'text-error-500' => $item->is_active && $item->current_stock <= $item->min_stock])>
                                {{ number_format($item->current_stock, 0, ',', '.') }}
                            </span>
                        </td>
                        <td class="table-td whitespace-nowrap">
                            @if ($badge)
                                <x-ui.badge size="sm" :color="$badge['color']">{{ $badge['label'] }}</x-ui.badge>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="table-td">@include('pages.master.partials.boolean-icon', ['value' => $item->is_active])</td>
                        <td class="table-td text-end">
                            <x-common.row-actions>
                                <x-ui.dropdown-menu.item x-on:click="load({{ Js::from(route('items.history', $item)) }}, 'historyOpen', {{ Js::from('Histori Aktivitas: '.$item->name) }})">
                                    <x-svg.time /> Histori
                                </x-ui.dropdown-menu.item>
                                <x-ui.dropdown-menu.item x-on:click="{{ $detailCall }}">
                                    <x-svg.eye /> Lihat
                                </x-ui.dropdown-menu.item>
                                <x-ui.dropdown-menu.item to="{{ route('items.edit', $item) }}">
                                    <x-svg.pencil /> Ubah
                                </x-ui.dropdown-menu.item>
                            </x-common.row-actions>
                        </td>
                    </tr>
                @empty
                    <x-common.table-empty :colspan="$usesSuppliers ? 9 : 8" title="Belum ada barang" />
                @endforelse
            </tbody>
        </x-common.table-card>

        {{-- Modal Histori --}}
        <x-common.modal show="historyOpen" max-width="3xl">
            <x-slot:heading><span x-text="panelTitle"></span></x-slot:heading>
            <div x-show="loading" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Memuat...</div>
            <div x-show="!loading" x-html="panelHtml"></div>
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="historyOpen = false">Tutup</x-ui.button>
            </x-slot:footer>
        </x-common.modal>

        {{-- Panel Lihat (slide-over) --}}
        <x-common.slide-over show="detailOpen" max-width="6xl">
            <x-slot:heading><span x-text="panelTitle"></span></x-slot:heading>
            <div x-show="loading" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">Memuat...</div>
            <div x-show="!loading" x-html="panelHtml"></div>
            <x-slot:footer>
                <x-ui.button variant="outline" x-on:click="detailOpen = false">Tutup</x-ui.button>
            </x-slot:footer>
        </x-common.slide-over>
    </div>
@endsection

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Waste / Bahan Terbuang" />

    {{-- id="waste-log-page": diperbarui ajaxForm setelah waste dicatat (form kosong lagi + riwayat & stok terbaru). --}}
    <div id="waste-log-page" class="grid grid-cols-1 gap-6 lg:grid-cols-[360px_1fr]">
        <x-common.component-card title="Catat Waste / Spoilage" desc="Bahan baku basi, tumpah, atau salah masak. Stok otomatis terpotong.">
            <form method="POST" action="{{ route('waste-logs.store') }}" class="space-y-4"
                x-data="ajaxForm({ regions: ['waste-log-page'] })" x-on:submit.prevent="submit">
                @csrf
                <x-common.form.field label="Barang / Bahan Baku" for="wl_item" required error="item_id">
                    <select id="wl_item" name="item_id" required
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="">-- Pilih Barang --</option>
                        @foreach ($items as $item)
                            <option value="{{ $item->id }}" @selected(old('item_id') == $item->id)>
                                {{ $item->name }} (Stok: {{ (float) $item->current_stock }} {{ $item->unit?->name }})
                            </option>
                        @endforeach
                    </select>
                </x-common.form.field>

                <div class="grid grid-cols-2 gap-3">
                    <x-common.form.field label="Jumlah" for="wl_qty" required error="quantity">
                        <x-ui.input id="wl_qty" type="number" name="quantity" value="{{ old('quantity') }}" step="0.0001" min="0.0001" required />
                    </x-common.form.field>
                    <x-common.form.field label="Alasan" for="wl_reason" required error="reason">
                        <x-common.form.select id="wl_reason" name="reason" :options="$reasons" :value="old('reason', 'spillage')" />
                    </x-common.form.field>
                </div>

                <x-common.form.field label="Catatan (Opsional)" for="wl_notes">
                    <x-ui.textarea id="wl_notes" name="notes" rows="2" placeholder="Keterangan tambahan">{{ old('notes') }}</x-ui.textarea>
                </x-common.form.field>

                <x-ui.button type="submit" class="w-full" x-bind:disabled="busy">
                        <span x-text="busy ? 'Menyimpan…' : 'Simpan Catatan Waste'">Simpan Catatan Waste</span>
                    </x-ui.button>
            </form>
        </x-common.component-card>

        <x-common.table-card title="Riwayat Waste / Spoilage" :paginator="$logs">
            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th">Waktu</th>
                    <th class="table-th">Barang / Bahan</th>
                    <th class="table-th">Jumlah</th>
                    <th class="table-th">Alasan</th>
                    <th class="table-th">Nilai Kerugian</th>
                    <th class="table-th">Petugas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($logs as $log)
                    <tr class="table-row">
                        <td class="table-td text-xs text-gray-500 dark:text-gray-400">{{ $log->wasted_at->format('d/m/Y H:i') }}</td>
                        <td class="table-td font-medium text-gray-800 dark:text-white/90">{{ $log->item?->name ?? '-' }}</td>
                        <td class="table-td">{{ (float) $log->quantity }} {{ $log->item?->unit?->name }}</td>
                        <td class="table-td">
                            <x-ui.badge size="sm" color="warning">{{ $reasons[$log->reason] ?? $log->reason }}</x-ui.badge>
                            @if ($log->notes)
                                <p class="mt-0.5 text-xs text-gray-400">{{ $log->notes }}</p>
                            @endif
                        </td>
                        <td class="table-td font-medium text-error-500">Rp{{ number_format($log->total_cost, 0, ',', '.') }}</td>
                        <td class="table-td text-gray-500 dark:text-gray-400">{{ $log->createdBy?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-6 py-10 text-center text-sm text-gray-400">Belum ada catatan waste.</td></tr>
                @endforelse
            </tbody>
        </x-common.table-card>
    </div>
@endsection

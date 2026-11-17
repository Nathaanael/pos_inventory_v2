@extends('layouts.kasir')

@section('content')
    {{-- id="shift-page": region yang diperbarui ajaxForm setelah buka / tutup shift. --}}
    <div id="shift-page" class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Shift Kasir & Laci Kas</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Kelola modal awal, kas masuk/keluar, dan tutup shift (Z-Report).</p>
            </div>
            @if ($activeShift)
                <form method="POST" action="{{ route('kasir.shift.print-report', $activeShift) }}" x-data="ajaxForm()" x-on:submit.prevent="submit">
                    @csrf
                    <x-ui.button type="submit" variant="outline" x-bind:disabled="busy">
                        <span x-text="busy ? 'Mencetak…' : 'Cetak X-Report (Thermal)'">Cetak X-Report (Thermal)</span>
                    </x-ui.button>
                </form>
            @endif
        </div>

        @if (! $activeShift)
            {{-- Form Buka Shift --}}
            <div class="max-w-lg rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Buka Shift Baru</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Masukkan jumlah uang tunai awal di laci kas sebelum mulai melayani transaksi.</p>

                <form method="POST" action="{{ route('kasir.shift.open') }}" class="mt-5 space-y-4"
                    x-data="ajaxForm({ regions: ['shift-page'] })" x-on:submit.prevent="submit">
                    @csrf
                    <x-common.form.field label="Modal Awal Kas (Rp)" for="opening_cash" required>
                        <x-common.form.money id="opening_cash" name="opening_cash" :value="old('opening_cash', 0)" :invalid="$errors->has('opening_cash')" required />
                    </x-common.form.field>
                    <x-common.form.field label="Catatan (Opsional)" for="open_notes">
                        <x-ui.input id="open_notes" type="text" name="notes" value="{{ old('notes') }}" placeholder="mis. Shift pagi" />
                    </x-common.form.field>
                    <x-ui.button type="submit" class="w-full" x-bind:disabled="busy">
                        <span x-text="busy ? 'Membuka shift…' : 'Buka Shift Sekarang'">Buka Shift Sekarang</span>
                    </x-ui.button>
                </form>
            </div>
        @else
            {{-- Ringkasan Shift Aktif (X-Report). id = region yang diperbarui ajaxForm setelah mutasi kas. --}}
            <div id="shift-summary" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Modal Awal</p>
                    <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">Rp{{ number_format($summary['opening_cash'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-gray-400">Dibuka {{ $activeShift->opened_at->format('d/m H:i') }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Total Omzet Shift</p>
                    <p class="mt-2 text-2xl font-bold text-brand-600 dark:text-brand-400">Rp{{ number_format($summary['total_sales'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ $summary['orders_count'] }} transaksi selesai</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Kas Masuk / Keluar</p>
                    <p class="mt-2 text-lg font-bold text-success-600 dark:text-success-400">+Rp{{ number_format($summary['cash_in'], 0, ',', '.') }}</p>
                    <p class="text-sm font-semibold text-error-500">-Rp{{ number_format($summary['cash_out'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    <p class="text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Estimasi Kas di Laci</p>
                    @if ($showExpected)
                        <p class="mt-2 text-2xl font-bold text-gray-800 dark:text-white/90">Rp{{ number_format($summary['expected_cash'], 0, ',', '.') }}</p>
                        <p class="mt-1 text-xs text-gray-400">Penjualan tunai: Rp{{ number_format($summary['cash_sales'], 0, ',', '.') }}</p>
                    @else
                        {{-- Blind closing: kasir menghitung uang tanpa melihat angka sistem. --}}
                        <p class="mt-2 text-2xl font-bold text-gray-300 dark:text-gray-600">Rp •••••</p>
                        <p class="mt-1 text-xs text-gray-400">Tampil setelah shift ditutup</p>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {{-- Kas Masuk / Keluar --}}
                {{-- Disimpan lewat AJAX + loading bar; kartu ini & ringkasan diperbarui tanpa reload. --}}
                <div id="shift-petty-cash" class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Catat Kas Masuk / Keluar (Petty Cash)</h3>
                    <form method="POST" action="{{ route('kasir.shift.cash-movement') }}" class="mt-4 space-y-3"
                        x-data="ajaxForm({ regions: ['shift-summary', 'shift-petty-cash'] })" x-on:submit.prevent="submit">
                        @csrf
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <x-common.form.field label="Jenis" for="cm_type">
                                <x-common.form.select id="cm_type" name="type" :options="['out' => 'Kas Keluar', 'in' => 'Kas Masuk']" value="out" />
                            </x-common.form.field>
                            <x-common.form.field label="Nominal" for="cm_amount" error="amount" required>
                                <x-common.form.money id="cm_amount" name="amount" placeholder="0" required />
                            </x-common.form.field>
                        </div>
                        <x-common.form.field label="Keterangan" for="cm_reason" error="reason" required>
                            <x-ui.input id="cm_reason" type="text" name="reason" placeholder="mis. Beli es batu, tambah uang kembalian" required />
                        </x-common.form.field>
                        <x-ui.button type="submit" variant="outline" x-bind:disabled="busy">
                            <span x-text="busy ? 'Menyimpan…' : 'Simpan Mutasi Kas'">Simpan Mutasi Kas</span>
                        </x-ui.button>
                    </form>

                    @if ($movements->isNotEmpty())
                        <div class="mt-5 divide-y divide-gray-100 border-t border-gray-100 pt-3 dark:divide-gray-800 dark:border-gray-800">
                            @foreach ($movements as $m)
                                <div class="flex items-center justify-between py-2 text-sm">
                                    <div>
                                        <span class="font-medium {{ $m->type === 'in' ? 'text-success-600' : 'text-error-500' }}">
                                            {{ $m->type === 'in' ? '+ Masuk' : '- Keluar' }}
                                        </span>
                                        <span class="ms-2 text-gray-700 dark:text-gray-300">{{ $m->reason }}</span>
                                    </div>
                                    <span class="font-semibold text-gray-800 dark:text-white/90">Rp{{ number_format($m->amount, 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Tutup Shift (Blind Closing) --}}
                <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                    <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Tutup Shift (Z-Report)</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Hitung uang fisik di laci kas per pecahan (atau isi totalnya) untuk menutup shift.</p>

                    <form method="POST" action="{{ route('kasir.shift.close') }}" class="mt-4 space-y-4"
                        x-data="ajaxForm({ regions: ['shift-page'] })" x-on:submit.prevent="submit">
                        @csrf
                        <x-main.cash-count.denomination-input id-prefix="close" />
                        <x-common.form.field label="Catatan Penutupan (Opsional)" for="close_notes" error="notes">
                            <x-ui.input id="close_notes" type="text" name="notes" placeholder="Catatan selisih / serah terima" />
                        </x-common.form.field>
                        <x-ui.button type="submit" class="w-full bg-error-500 hover:bg-error-600" x-bind:disabled="busy">
                            <span x-text="busy ? 'Menutup shift…' : 'Tutup Shift & Simpan Z-Report'">Tutup Shift & Simpan Z-Report</span>
                        </x-ui.button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Admin: shift kasir lain yang sedang berjalan --}}
        @if ($otherActiveShifts->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">Shift Kasir Lain yang Sedang Berjalan</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $otherActiveShifts->count() }} shift aktif</p>
                    </div>
                    @if (\App\Models\StoreSetting::usesCashCount())
                        <x-ui.button as="a" href="{{ route('cash-counts.index') }}" variant="outline" size="sm">Buka Hitung Kas</x-ui.button>
                    @endif
                </div>
                <div class="mt-4 divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($otherActiveShifts as $other)
                        <div class="flex items-center justify-between gap-3 py-2.5 text-sm">
                            <span class="font-medium text-gray-800 dark:text-white/90">{{ $other->user?->name ?? '-' }}</span>
                            <span class="text-gray-500 dark:text-gray-400">Buka {{ $other->opened_at->format('d/m H:i') }} · {{ $other->outbounds_count }} transaksi</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Riwayat Shift --}}
        <x-common.table-card title="Riwayat Shift Sebelumnya" :paginator="$history">
            <thead class="border-y border-gray-100 dark:border-gray-800">
                <tr>
                    <th class="table-th">Kasir</th>
                    <th class="table-th">Buka - Tutup</th>
                    <th class="table-th">Modal Awal</th>
                    <th class="table-th">Est. Kas</th>
                    <th class="table-th">Kas Aktual</th>
                    <th class="table-th">Selisih</th>
                    <th class="table-th w-28"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($history as $row)
                    <tr class="table-row">
                        <td class="table-td font-medium text-gray-800 dark:text-white/90">{{ $row->user?->name ?? '-' }}</td>
                        <td class="table-td text-xs text-gray-500 dark:text-gray-400">
                            {{ $row->opened_at->format('d/m/Y H:i') }} – {{ $row->closed_at?->format('H:i') }}
                        </td>
                        <td class="table-td">Rp{{ number_format($row->opening_cash, 0, ',', '.') }}</td>
                        <td class="table-td">Rp{{ number_format($row->expected_cash, 0, ',', '.') }}</td>
                        <td class="table-td font-medium">Rp{{ number_format($row->actual_cash, 0, ',', '.') }}</td>
                        <td class="table-td font-semibold {{ $row->cash_difference < 0 ? 'text-error-500' : ($row->cash_difference > 0 ? 'text-success-600' : 'text-gray-500') }}">
                            {{ $row->cash_difference >= 0 ? '+' : '' }}Rp{{ number_format($row->cash_difference, 0, ',', '.') }}
                        </td>
                        <td class="table-td text-end">
                            <form method="POST" action="{{ route('kasir.shift.print-report', $row) }}" x-data="ajaxForm()" x-on:submit.prevent="submit">
                                @csrf
                                <x-ui.button type="submit" size="sm" variant="outline" x-bind:disabled="busy">
                                    <span x-text="busy ? 'Mencetak…' : 'Cetak Z-Report'">Cetak Z-Report</span>
                                </x-ui.button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-6 py-8 text-center text-sm text-gray-400">Belum ada riwayat shift yang ditutup.</td></tr>
                @endforelse
            </tbody>
        </x-common.table-card>
    </div>
@endsection

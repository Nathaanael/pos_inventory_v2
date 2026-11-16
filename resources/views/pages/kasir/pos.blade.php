@extends('layouts.kasir')

{{-- Layar kasir (dulu App\Filament\Kasir\Pages\Pos). Logika di resources/js/components/kasir/kasir-pos.js --}}
@php
    $segBtn = 'flex h-10 flex-1 items-center justify-center gap-1.5 rounded-lg text-sm font-medium transition-colors';
    $segOn = "'bg-white text-gray-800 shadow-theme-xs dark:bg-gray-800 dark:text-white/90'";
    $segOff = "'text-gray-500 hover:text-gray-700 dark:text-gray-400'";
@endphp

@section('content')
    <div x-data="kasirPos({
            catalog: @js($catalog),
            categories: @js($categories),
            recent: @js($recent),
            fnb: @js($fnb),
            tables: @js($tables),
            preselectTable: @js($preselectTable),
            checkoutUrl: @js(route('kasir.checkout')),
        })"
        x-on:keydown.window="shortcut($event)"
        class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_400px]">

        {{-- ================= KIRI: cari, kategori, barang ================= --}}
        <div class="flex min-w-0 flex-col gap-4">
            <x-ui.input-group class="h-12 bg-white dark:bg-white/3">
                <x-ui.input-group.addon align="inline-start">
                    <x-svg.search class="size-5 text-gray-400" />
                </x-ui.input-group.addon>
                <x-ui.input-group.input x-ref="search" type="text" x-model="search" x-on:keydown.enter.prevent="onEnter()"
                    placeholder="Cari nama / kode, atau scan barcode lalu Enter" autocomplete="off" />
                <x-ui.input-group.addon align="inline-end">
                    <span class="rounded-md border border-gray-200 px-1.5 text-xs text-gray-400 dark:border-gray-700">/</span>
                </x-ui.input-group.addon>
            </x-ui.input-group>

            {{-- Kategori --}}
            <div class="no-scrollbar flex gap-3 overflow-x-auto pb-1">
                <button type="button" x-on:click="activeCategory = null"
                    class="flex shrink-0 items-center gap-3 rounded-xl border-2 bg-gray-100 px-3 py-2.5 text-start transition dark:bg-white/5"
                    x-bind:class="activeCategory === null ? 'border-brand-500' : 'border-transparent'">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-white text-sm font-bold text-gray-700 dark:bg-gray-900 dark:text-gray-300">∗</span>
                    <span>
                        <span class="block text-sm font-semibold text-gray-800 dark:text-white/90">Semua</span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="catalog.length + ' barang'"></span>
                    </span>
                </button>
                <template x-for="cat in categories" :key="cat.id">
                    <button type="button" x-on:click="activeCategory = activeCategory === cat.id ? null : cat.id"
                        class="flex shrink-0 items-center gap-3 rounded-xl border-2 px-3 py-2.5 text-start transition"
                        x-bind:class="[toneFor(cat.id), activeCategory === cat.id ? 'border-brand-500' : 'border-transparent']">
                        <span class="flex size-8 items-center justify-center rounded-lg bg-white text-sm font-bold text-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            x-text="cat.name.charAt(0).toUpperCase()"></span>
                        <span>
                            <span class="block text-sm font-semibold whitespace-nowrap text-gray-800 dark:text-white/90" x-text="cat.name"></span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400" x-text="countIn(cat.id) + ' barang'"></span>
                        </span>
                    </button>
                </template>
            </div>

            {{-- Barang --}}
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 2xl:grid-cols-4">
                <template x-for="item in filtered" :key="item.id">
                    <div role="button" tabindex="0" x-on:click="add(item)" x-on:keydown.enter="add(item)"
                        class="relative flex cursor-pointer flex-col gap-1 rounded-xl border-2 p-3 transition hover:-translate-y-0.5 hover:shadow-theme-md"
                        x-bind:class="[toneFor(item.category_id), qtyOf(item.id) > 0 ? 'border-brand-500' : 'border-transparent', item.stock <= 0 && 'cursor-not-allowed opacity-50 hover:translate-y-0 hover:shadow-none']">
                        <img x-show="item.image" x-bind:src="item.image" alt="" loading="lazy" class="absolute end-3 top-3 size-9 rounded-lg object-cover" />
                        <span class="text-xs text-gray-500 dark:text-gray-400" x-text="categoryName(item.category_id)"></span>
                        <span class="line-clamp-2 min-h-10 text-sm font-semibold text-gray-800 dark:text-white/90" x-bind:class="item.image && 'pe-10'" x-text="item.name"></span>
                        <span class="text-base font-bold text-gray-800 dark:text-white/90" x-text="money(price(item))"></span>
                        <div class="mt-1 flex items-center justify-between gap-2">
                            <span class="text-xs text-gray-500 dark:text-gray-400" x-bind:title="'Stok tersedia: ' + item.stock"
                                x-text="item.stock > 0 ? item.stock + (item.unit ? ' ' + item.unit : '') : 'Habis'"></span>
                            <div class="flex items-center overflow-hidden rounded-lg bg-white dark:bg-gray-900" x-on:click.stop>
                                <button type="button" class="flex size-7 items-center justify-center text-gray-600 disabled:opacity-30 dark:text-gray-400"
                                    x-bind:disabled="qtyOf(item.id) === 0" x-on:click="dec(item.id)" aria-label="Kurangi">−</button>
                                <span class="w-6 text-center text-sm font-semibold text-gray-800 dark:text-white/90" x-text="qtyOf(item.id)"></span>
                                <button type="button" class="flex size-7 items-center justify-center text-gray-600 disabled:opacity-30 dark:text-gray-400"
                                    x-bind:disabled="item.stock <= 0" x-on:click="add(item)" aria-label="Tambah">+</button>
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="filtered.length === 0" class="col-span-full py-16 text-center text-sm text-gray-500 dark:text-gray-400">Barang tidak ditemukan.</div>
            </div>

            {{-- Transaksi terakhir hari ini --}}
            <div class="no-scrollbar flex items-center gap-3 overflow-x-auto rounded-2xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-white/3">
                <template x-for="order in recent" :key="order.id">
                    <div class="flex shrink-0 items-center gap-3 rounded-xl bg-gray-50 px-3 py-2 dark:bg-white/3">
                        <span class="flex size-9 items-center justify-center rounded-full bg-brand-50 text-xs font-semibold text-brand-500 dark:bg-brand-500/15" x-text="order.no"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium whitespace-nowrap text-gray-800 dark:text-white/90" x-text="order.customer"></p>
                            <p class="text-xs whitespace-nowrap text-gray-500 dark:text-gray-400"
                                x-text="(order.order ? order.order + ' · ' : '') + order.items + ' barang · ' + money(order.total) + ' · ' + order.time"></p>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                            x-bind:class="{
                                'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500': order.status === 'selesai',
                                'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400': order.status === 'pending',
                                'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500': order.status === 'voided',
                            }"
                            x-text="({ pending: 'Menunggu', selesai: 'Selesai', voided: 'Batal' })[order.status] ?? order.status"></span>
                        <a x-show="order.print_url" x-bind:href="order.print_url" target="_blank" class="text-xs font-medium text-brand-500 hover:underline">Nota</a>
                    </div>
                </template>
                <p x-show="!recent.length" class="px-1 py-1.5 text-sm text-gray-500 dark:text-gray-400">Belum ada transaksi hari ini.</p>
                <a x-show="recent.length" href="{{ route('kasir.riwayat') }}" class="ms-auto shrink-0 px-2 text-sm font-medium whitespace-nowrap text-brand-500 hover:underline">Lihat semua →</a>
            </div>
        </div>

        {{-- ================= KANAN: pesanan ================= --}}
        <div class="flex min-w-0 flex-col self-start overflow-hidden rounded-2xl border border-gray-200 bg-white xl:sticky xl:top-24 xl:max-h-[calc(100vh-7rem)] dark:border-gray-800 dark:bg-white/3">
            <div class="flex flex-col gap-3 border-b border-gray-100 p-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Pesanan Baru</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400" x-text="totalQty ? totalQty + ' barang di keranjang' : 'Klik barang untuk menambah'"></p>
                    </div>
                    <x-ui.button variant="outline" size="icon-sm" aria-label="Kosongkan keranjang" x-show="cart.length" x-on:click="clearCart()" class="text-error-500">
                        <x-svg.trash-bin class="size-5" />
                    </x-ui.button>
                </div>

                <x-ui.input x-model="customer" placeholder="Nama pelanggan (opsional)" maxlength="100" />

                {{-- FNB: makan di tempat / take away + nomor meja --}}
                <template x-if="hasFnb">
                    <div class="flex flex-col gap-3">
                        <div x-show="fnb.takeAway" class="flex gap-1 rounded-xl bg-gray-100 p-1 dark:bg-white/5">
                            <button type="button" class="{{ $segBtn }}" x-bind:class="orderType === 'dine_in' ? {{ $segOn }} : {{ $segOff }}" x-on:click="setOrderType('dine_in')">Makan di Tempat</button>
                            <button type="button" class="{{ $segBtn }}" x-bind:class="orderType === 'take_away' ? {{ $segOn }} : {{ $segOff }}" x-on:click="setOrderType('take_away')">Take Away</button>
                        </div>
                        <div x-show="needsTable">
                            <button type="button" x-on:click="openTablePicker()"
                                class="flex w-full items-center gap-3 rounded-xl border-2 p-3 text-start transition"
                                x-bind:class="selectedTable ? 'border-brand-500 bg-brand-50 dark:bg-brand-500/10' : (cart.length ? 'border-error-500 border-dashed' : 'border-dashed border-gray-300 dark:border-gray-700')">
                                <span class="flex size-11 shrink-0 items-center justify-center rounded-lg text-lg font-bold"
                                    x-bind:class="selectedTable ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-400 dark:bg-white/5'"
                                    x-text="selectedTable ? selectedTable.number : '#'"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold text-gray-800 dark:text-white/90" x-text="selectedTable ? 'Meja ' + selectedTable.number : 'Pilih Meja'"></span>
                                    <span class="block text-xs text-gray-500 dark:text-gray-400"
                                        x-text="selectedTable
                                            ? (selectedTable.name ? selectedTable.name + ' · ' : '') + (selectedTable.occupied ? 'tambah pesanan' : 'meja kosong')
                                            : (tables.length ? freeTableCount + ' meja kosong dari ' + tables.length : 'Belum ada meja aktif')"></span>
                                </span>
                                <span x-show="selectedTable" class="text-xs font-medium text-brand-500">Ganti</span>
                            </button>
                            <p x-show="!tables.length" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Belum ada meja aktif. Minta admin menambah di Master Meja.</p>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Daftar pesanan --}}
            <div class="custom-scrollbar min-h-32 flex-1 overflow-y-auto px-5 py-3">
                <template x-for="(line, index) in lines" :key="line.id">
                    <div class="flex items-start gap-3 border-b border-dashed border-gray-100 py-3 last:border-0 dark:border-gray-800">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-400" x-text="index + 1"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                                <span x-text="line.item.name"></span>
                                <small class="text-gray-500 dark:text-gray-400" x-text="' · ' + money(price(line.item))"></small>
                            </p>
                            <div class="mt-1.5 flex w-fit items-center overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                                <button type="button" class="flex size-7 items-center justify-center bg-gray-50 text-gray-600 dark:bg-white/5 dark:text-gray-400" x-on:click="dec(line.id)" aria-label="Kurangi">−</button>
                                <input type="number" min="1" x-bind:max="line.item.stock" x-bind:value="line.qty" x-on:change="setQty(line.id, $event.target.value)" aria-label="Jumlah"
                                    class="h-7 w-12 border-x border-gray-200 bg-white text-center text-sm text-gray-800 outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                <button type="button" class="flex size-7 items-center justify-center bg-gray-50 text-gray-600 dark:bg-white/5 dark:text-gray-400" x-on:click="add(line.item)" aria-label="Tambah">+</button>
                            </div>
                        </div>
                        <div class="text-end">
                            <p class="text-sm font-semibold whitespace-nowrap text-gray-800 dark:text-white/90" x-text="money(price(line.item) * line.qty)"></p>
                            <button type="button" class="mt-1 text-xs text-error-500 hover:underline" x-on:click="remove(line.id)">Hapus</button>
                        </div>
                    </div>
                </template>
                <div x-show="!cart.length" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    Keranjang kosong.<br><small>Klik barang, atau scan barcode di kolom cari.</small>
                </div>
            </div>

            {{-- Ringkasan & pembayaran --}}
            <div class="flex flex-col gap-4 border-t border-gray-100 bg-gray-50 p-5 dark:border-gray-800 dark:bg-white/2">
                <div class="space-y-1.5">
                    @if ($hasWholesale)
                        <div class="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                            <span>Harga</span>
                            <div class="flex w-44 gap-1 rounded-lg bg-gray-200/60 p-0.5 dark:bg-white/5">
                                <button type="button" class="{{ $segBtn }} h-7" x-bind:class="priceType === 'retail' ? {{ $segOn }} : {{ $segOff }}" x-on:click="priceType = 'retail'">Eceran</button>
                                <button type="button" class="{{ $segBtn }} h-7" x-bind:class="priceType === 'grosir' ? {{ $segOn }} : {{ $segOff }}" x-on:click="priceType = 'grosir'">Grosir</button>
                            </div>
                        </div>
                    @endif
                    <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400">
                        <span x-text="'Subtotal (' + totalQty + ' barang)'"></span>
                        <span x-text="money(total)"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-base font-semibold text-gray-800 dark:text-white/90">Total</span>
                        <span class="text-2xl font-bold text-gray-800 dark:text-white/90" x-text="money(total)"></span>
                    </div>
                </div>

                <div>
                    <p class="mb-2 text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">Metode Pembayaran</p>
                    <div class="flex gap-1 rounded-xl bg-gray-200/60 p-1 dark:bg-white/5">
                        @foreach (\App\Models\Outbound::PAYMENT_METHODS as $key => $label)
                            <button type="button" class="{{ $segBtn }}" x-bind:class="payment === @js($key) ? {{ $segOn }} : {{ $segOff }}"
                                x-on:click="payment = @js($key)">{{ ['tunai' => 'Tunai', 'debit' => 'Debit', 'qris' => 'QRIS'][$key] ?? $label }}</button>
                        @endforeach
                    </div>

                    <div x-show="payment === 'tunai'" class="mt-3 space-y-2">
                        <x-ui.input inputmode="numeric" placeholder="Uang diterima"
                            x-bind:value="paidNumber ? 'Rp' + new Intl.NumberFormat('id-ID').format(paidNumber) : ''"
                            x-on:input="paid = $event.target.value" x-on:keydown.enter.prevent="submit()" />
                        <div class="flex flex-wrap gap-2">
                            <template x-for="amount in quickAmounts" :key="amount">
                                <button type="button" x-on:click="paid = String(amount)" x-text="amount === total ? 'Uang pas' : money(amount)"
                                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-brand-300 hover:text-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"></button>
                            </template>
                        </div>
                        <div x-show="paidNumber > 0" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm"
                            x-bind:class="change < 0 ? 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500' : 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500'">
                            <span x-text="change < 0 ? 'Kurang' : 'Kembalian'"></span>
                            <strong x-text="money(Math.abs(change))"></strong>
                        </div>
                    </div>
                </div>

                <div>
                    <button type="button" class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400" x-on:click="showNotes = !showNotes"
                        x-text="showNotes ? '− Sembunyikan catatan' : '+ Tambah catatan'"></button>
                    <x-ui.textarea x-show="showNotes" x-model="notes" rows="2" maxlength="500" placeholder="Catatan transaksi" class="mt-2 min-h-16" />
                </div>

                <x-ui.button size="lg" class="h-12 w-full text-base" x-bind:disabled="!canSubmit" x-on:click="submit()">
                    <span x-text="saving ? 'Menyimpan…' : 'Simpan Transaksi'"></span>
                </x-ui.button>
                <p x-show="needsTable && cart.length && !selectedTable" class="-mt-2 text-center text-xs text-error-500">Pilih meja dulu · tekan Simpan untuk membuka daftar meja</p>
                <p class="-mt-2 text-center text-xs text-gray-400">Ctrl+Enter untuk simpan</p>
            </div>
        </div>

        {{-- ================= Pemilih meja ================= --}}
        <x-common.modal show="tablePickerOpen" max-width="4xl" title="Pilih Meja">
            <p class="-mt-4 mb-5 text-sm text-gray-500 dark:text-gray-400" x-text="freeTableCount + ' kosong · ' + (tables.length - freeTableCount) + ' terisi'"></p>
            <div class="mb-5 flex flex-col gap-3 sm:flex-row">
                <x-ui.input x-ref="tableSearch" x-model="tableSearch" x-on:keydown.enter.prevent="pickTableFromSearch()" placeholder="Ketik nomor meja lalu Enter" autocomplete="off" />
                <div class="flex shrink-0 gap-1 rounded-xl bg-gray-100 p-1 sm:w-72 dark:bg-white/5">
                    <button type="button" class="{{ $segBtn }} h-9" x-bind:class="tableFilter === 'all' ? {{ $segOn }} : {{ $segOff }}" x-on:click="tableFilter = 'all'">Semua</button>
                    <button type="button" class="{{ $segBtn }} h-9" x-bind:class="tableFilter === 'free' ? {{ $segOn }} : {{ $segOff }}" x-on:click="tableFilter = 'free'">Kosong</button>
                    <button type="button" class="{{ $segBtn }} h-9" x-bind:class="tableFilter === 'busy' ? {{ $segOn }} : {{ $segOff }}" x-on:click="tableFilter = 'busy'">Terisi</button>
                </div>
            </div>
            <div class="custom-scrollbar grid max-h-[55vh] grid-cols-2 gap-3 overflow-y-auto sm:grid-cols-4 lg:grid-cols-5">
                <template x-for="t in pickerTables" :key="t.id">
                    <button type="button" x-on:click="pickTable(t)"
                        class="relative flex flex-col items-center rounded-xl border-2 p-4 text-center transition hover:-translate-y-0.5"
                        x-bind:class="[
                            t.occupied ? 'bg-warning-50 dark:bg-warning-500/10' : 'bg-success-50 dark:bg-success-500/10',
                            t.id === tableId ? 'border-brand-500' : 'border-transparent',
                        ]">
                        <span class="text-2xl font-bold text-gray-800 dark:text-white/90" x-text="t.number"></span>
                        <span class="min-h-4 text-xs text-gray-500 dark:text-gray-400" x-text="t.name || ''"></span>
                        <span class="mt-1 text-xs font-medium" x-bind:class="t.occupied ? 'text-warning-600 dark:text-orange-400' : 'text-success-600 dark:text-success-500'"
                            x-text="t.occupied ? 'Terisi · tambah pesanan' : 'Kosong'"></span>
                        <span x-show="t.id === tableId" class="absolute end-2 top-2 flex size-5 items-center justify-center rounded-full bg-brand-500 text-white">
                            <x-svg.check class="size-3" />
                        </span>
                    </button>
                </template>
                <p x-show="!pickerTables.length" class="col-span-full py-10 text-center text-sm text-gray-500 dark:text-gray-400">Meja tidak ditemukan.</p>
            </div>
        </x-common.modal>

        {{-- ================= Konfirmasi kosongkan keranjang ================= --}}
        <x-common.modal show="confirmOpen" close="confirmClear(false)" max-width="md">
            <div class="flex flex-col items-center text-center">
                <span class="mb-5 flex size-14 items-center justify-center rounded-full bg-error-50 text-error-500 dark:bg-error-500/15">
                    <x-svg.trash-bin class="size-7" />
                </span>
                <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90">Kosongkan keranjang?</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" x-text="totalQty + ' barang senilai ' + money(total) + ' akan dihapus dari pesanan.'"></p>
            </div>
            <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
                <x-ui.button variant="outline" class="sm:min-w-36" x-on:click="confirmClear(false)">Batal <small class="text-gray-400">Esc</small></x-ui.button>
                <x-ui.button variant="destructive" class="sm:min-w-36" x-on:click="confirmClear(true)">Ya, kosongkan <small class="opacity-75">Enter</small></x-ui.button>
            </div>
        </x-common.modal>
    </div>
@endsection

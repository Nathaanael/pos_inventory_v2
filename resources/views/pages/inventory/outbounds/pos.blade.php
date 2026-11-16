@extends('layouts.app')

{{-- Layar POS "Tambah Barang Keluar" (dulu CreateOutboundPos + InteractsWithPosCart). --}}
@section('content')
    <div x-data="posCart({
            itemsUrl: @js(route('outbounds.pos-items')),
            storeUrl: @js(route('outbounds.store')),
            date: @js(now()->format('Y-m-d')),
        })"
        class="grid grid-cols-1 gap-6 lg:grid-cols-[58fr_42fr]">

        {{-- ═══════ Kiri: katalog barang ═══════ --}}
        <div class="flex min-w-0 flex-col gap-4">
            <div>
                <h2 class="mb-2 text-xl font-semibold text-gray-800 dark:text-white/90">Tambah Barang Keluar</h2>
                <x-ui.badge color="primary">{{ $code }}</x-ui.badge>
            </div>

            <x-ui.input-group class="h-12">
                <x-ui.input-group.addon align="inline-start">
                    <x-svg.search class="size-5 text-gray-400" />
                </x-ui.input-group.addon>
                <x-ui.input-group.input type="search" x-model="search" x-on:keydown.enter.prevent="addFromSearch()"
                    placeholder="Cari barang (nama, kode, barcode)..." autocomplete="off" autofocus />
            </x-ui.input-group>

            {{-- Kategori --}}
            <div class="no-scrollbar flex gap-2 overflow-x-auto pb-1">
                <button type="button" x-on:click="category = null"
                    class="shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium whitespace-nowrap transition-colors"
                    x-bind:class="category === null ? 'border-brand-500 bg-brand-500 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/3 dark:text-gray-400'">
                    Semua
                </button>
                @foreach ($categories as $category)
                    <button type="button" x-on:click="category = {{ $category->id }}"
                        class="shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium whitespace-nowrap transition-colors"
                        x-bind:class="category === {{ $category->id }} ? 'border-brand-500 bg-brand-500 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50 dark:border-gray-800 dark:bg-white/3 dark:text-gray-400'">
                        {{ $category->name }}
                    </button>
                @endforeach
            </div>

            {{-- Grid barang --}}
            <div class="grid grid-cols-2 content-start gap-3 transition-opacity sm:grid-cols-3 xl:grid-cols-4" x-bind:class="loading && 'opacity-50'">
                <template x-for="item in catalog" :key="item.id">
                    <button type="button" x-on:click="add(item)"
                        class="group relative flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white text-start transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-theme-md dark:border-gray-800 dark:bg-white/3 dark:hover:border-brand-500/40"
                        x-bind:class="item.stock <= 0 && 'cursor-not-allowed opacity-45 hover:translate-y-0 hover:border-gray-200 hover:shadow-none'">
                        <div class="flex h-28 w-full items-center justify-center border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                            <template x-if="item.image">
                                <img x-bind:src="item.image" x-bind:alt="item.name" class="h-full w-full object-cover" />
                            </template>
                            <template x-if="!item.image">
                                <svg class="size-12 stroke-current text-gray-300 dark:text-gray-700" viewBox="0 0 24 24" fill="none" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                </svg>
                            </template>
                        </div>
                        <span class="absolute end-2 top-2 rounded-full px-2 py-0.5 text-xs font-semibold"
                            x-bind:class="{
                                'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500': stockClass(item) === 'ok',
                                'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-orange-400': stockClass(item) === 'low',
                                'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-500': stockClass(item) === 'no',
                            }"
                            x-text="item.stock <= 0 ? 'Habis' : `${item.stock} ${item.unit}`"></span>
                        <div class="flex flex-col gap-1 p-3">
                            <span class="line-clamp-2 text-sm font-semibold text-gray-800 dark:text-white/90" x-text="item.name"></span>
                            <span class="text-xs text-gray-400" x-text="item.code"></span>
                            <span class="text-sm font-bold text-brand-500 dark:text-brand-400" x-text="rupiah(price(item))"></span>
                        </div>
                    </button>
                </template>

                <div x-show="!loading && !catalog.length" x-cloak class="col-span-full flex flex-col items-center py-16 text-center text-gray-400">
                    <x-svg.search class="mb-3 size-10" />
                    <p class="font-medium">Barang tidak ditemukan</p>
                    <p class="mt-1 text-xs">Coba kata kunci atau kategori lain</p>
                </div>
            </div>
        </div>

        {{-- ═══════ Kanan: keranjang & form ═══════ --}}
        <div class="flex min-w-0 flex-col self-start overflow-hidden rounded-2xl border border-gray-200 bg-white lg:sticky lg:top-24 dark:border-gray-800 dark:bg-white/3">
            <div class="flex flex-col gap-4 p-5">
                <div class="flex items-center justify-between">
                    <svg class="size-6 stroke-current text-brand-500" viewBox="0 0 24 24" fill="none" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                    </svg>
                    <div class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-400">
                        <x-svg.calendar class="size-4" />
                        <button type="button" x-show="!editingDate" x-on:click="editingDate = true; $nextTick(() => $refs.dateInput.focus())"
                            class="font-medium hover:text-brand-500" x-text="dateLabel"></button>
                        <input type="date" x-ref="dateInput" x-show="editingDate" x-cloak x-model="date"
                            x-on:blur="editingDate = false" x-on:change="editingDate = false"
                            class="rounded-md border border-gray-200 px-2 py-0.5 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <x-common.form.field label="Pelanggan" for="pos-customer">
                        <x-ui.input id="pos-customer" x-model="customer_name" placeholder="Nama pelanggan..." class="h-10" />
                    </x-common.form.field>
                    <x-common.form.field label="Tujuan" for="pos-destination">
                        <x-ui.input id="pos-destination" x-model="destination" placeholder="Tujuan pengiriman..." class="h-10" />
                    </x-common.form.field>
                    @if ($hasWholesale)
                        <x-common.form.field label="Tipe Harga" for="pos-price-type">
                            <x-common.form.select id="pos-price-type" x-model="priceType" :options="['retail' => 'Retail', 'grosir' => 'Grosir']" class="h-10" />
                        </x-common.form.field>
                    @endif
                </div>

                <hr class="border-gray-100 dark:border-gray-800" />

                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-white/90">
                        Keranjang
                        <span x-show="cart.length" x-text="cart.length" class="rounded-full bg-brand-500 px-2 py-0.5 text-xs font-bold text-white"></span>
                    </div>
                    <button type="button" x-show="cart.length" x-on:click="clear()" class="text-xs font-medium text-error-500 hover:text-error-600">Hapus Semua</button>
                </div>

                <div class="custom-scrollbar flex max-h-[35vh] flex-col gap-2 overflow-y-auto">
                    <template x-for="line in cart" :key="line.id">
                        <div class="flex items-center gap-3 rounded-lg border border-gray-100 bg-gray-50 p-2.5 dark:border-gray-800 dark:bg-white/3">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-gray-800 dark:text-white/90" x-text="line.name" x-bind:title="line.name"></p>
                                <p class="text-xs text-gray-500 dark:text-gray-400" x-text="`${rupiah(price(line))} / ${line.unit}`"></p>
                            </div>
                            <div class="flex items-center overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                                <button type="button" x-on:click="setQty(line, line.quantity - 1)" class="flex size-7 items-center justify-center bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-400" aria-label="Kurangi">−</button>
                                <input type="number" min="1" x-bind:value="line.quantity" x-on:change="setQty(line, $event.target.value); $event.target.value = line.quantity"
                                    class="h-7 w-11 border-x border-gray-200 bg-white text-center text-sm text-gray-800 outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" aria-label="Jumlah" />
                                <button type="button" x-on:click="setQty(line, line.quantity + 1)" class="flex size-7 items-center justify-center bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-400" aria-label="Tambah">+</button>
                            </div>
                            <span class="w-24 text-end text-sm font-semibold whitespace-nowrap text-gray-800 dark:text-white/90" x-text="rupiah(lineTotal(line))"></span>
                            <button type="button" x-on:click="remove(line)" class="text-gray-400 hover:text-error-500" aria-label="Hapus">
                                <x-svg.x class="size-4" />
                            </button>
                        </div>
                    </template>
                    <div x-show="!cart.length" class="flex flex-col items-center py-8 text-center text-gray-400">
                        <p class="font-medium">Keranjang kosong</p>
                        <p class="mt-1 text-xs">Klik produk untuk menambahkan</p>
                    </div>
                </div>

                <x-common.form.field label="Catatan" for="pos-notes">
                    <x-ui.textarea id="pos-notes" x-model="notes" rows="2" placeholder="Catatan tambahan..." />
                </x-common.form.field>
            </div>

            {{-- Ringkasan --}}
            <div class="space-y-2 border-t border-gray-100 bg-gray-50 p-5 dark:border-gray-800 dark:bg-white/2">
                <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400">
                    <span x-text="`Subtotal (${cart.length} item)`"></span>
                    <span class="text-gray-800 dark:text-white/90" x-text="rupiah(totalAmount)"></span>
                </div>
                <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400">
                    <span>Total HPP</span>
                    <span class="text-gray-800 dark:text-white/90" x-text="rupiah(totalCogs)"></span>
                </div>
                <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400">
                    <span>Margin</span>
                    <span class="text-success-600 dark:text-success-500" x-text="margin > 0 ? rupiah(margin) : '-'"></span>
                </div>
                <div class="flex items-center justify-between border-t border-dashed border-gray-200 pt-3 dark:border-gray-700">
                    <span class="text-base font-semibold text-gray-800 dark:text-white/90">Total</span>
                    <span class="text-xl font-bold text-brand-500 dark:text-brand-400" x-text="rupiah(totalAmount)"></span>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-3">
                    <x-ui.button as="a" href="{{ route('outbounds.index') }}" variant="outline" size="lg">
                        <x-svg.x class="size-5" /> Batal
                    </x-ui.button>
                    <x-ui.button size="lg" x-on:click="save()" x-bind:disabled="saving || !cart.length">
                        <x-svg.check class="size-5" />
                        <span x-text="saving ? 'Menyimpan...' : 'Simpan'"></span>
                    </x-ui.button>
                </div>
            </div>
        </div>
    </div>
@endsection

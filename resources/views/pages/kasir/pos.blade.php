@extends('layouts.kasir')

{{-- Layar kasir (dulu App\Filament\Kasir\Pages\Pos). Logika di resources/js/components/kasir/kasir-pos.js --}}
@php
    $segBtn = 'flex h-10 flex-1 items-center justify-center gap-1.5 rounded-lg text-sm font-medium transition-colors';
    $segOn = "'bg-white text-gray-800 shadow-theme-xs dark:bg-gray-800 dark:text-white/90'";
    $segOff = "'text-gray-500 hover:text-gray-700 dark:text-gray-400'";
@endphp

@section('content')
    @if ($shiftRequired)
        {{-- Modul FNB: transaksi diblokir sampai shift dibuka (server juga menolak checkout). --}}
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-400">
            <span><strong>Shift kasir belum dibuka.</strong> Transaksi tidak bisa disimpan sebelum shift dibuka, agar uang masuk tercatat di laci kas.</span>
            <a href="{{ route('kasir.shift.index') }}" class="rounded-lg bg-error-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-error-600">Buka Shift</a>
        </div>
    @elseif (! $activeShift)
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
            <span>Shift kasir belum dibuka. Buka shift terlebih dahulu agar transaksi tercatat dalam laporan laci kas (X/Z-Report).</span>
            <a href="{{ route('kasir.shift.index') }}" class="rounded-lg bg-warning-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-warning-600">Buka Shift</a>
        </div>
    @endif
    <div x-data="kasirPos({
            catalog: @js($catalog),
            categories: @js($categories),
            recent: @js($recent),
            openBills: @js($openBills),
            memberSearchUrl: @js(route('kasir.members.search')),
            memberStoreUrl: @js(route('kasir.members.store')),
            fnb: @js($fnb),
            tables: @js($tables),
            preselectTable: @js($preselectTable),
            activeOrder: @js($activeOrder),
            usesModifiers: @js($usesModifiers),
            shiftRequired: @js($shiftRequired),
            checkoutUrl: @js(route('kasir.checkout')),
            addItemsUrlTemplate: @js(route('kasir.orders.add-items', '__ID__')),
            mejaUrl: @js(route('kasir.meja')),
            taxRules: @js($taxRules),
        })"
        x-on:keydown.window="shortcut($event)"
        x-on:bill-paid.window="onBillPaid($event.detail)"
        class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_400px]">

        {{-- ================= KIRI: cari, kategori, barang ================= --}}
        <div class="flex min-w-0 flex-col gap-4">
            <div class="flex gap-3">
            <x-ui.input-group class="h-12 min-w-0 flex-1 bg-white dark:bg-white/3">
                <x-ui.input-group.addon align="inline-start">
                    <x-svg.search class="size-5 text-gray-400" />
                </x-ui.input-group.addon>
                <x-ui.input-group.input x-ref="search" type="text" x-model="search" x-on:keydown.enter.prevent="onEnter()"
                    placeholder="Cari nama / kode, atau scan barcode lalu Enter" autocomplete="off" />
                <x-ui.input-group.addon align="inline-end">
                    <span class="rounded-md border border-gray-200 px-1.5 text-xs text-gray-400 dark:border-gray-700">/</span>
                </x-ui.input-group.addon>
            </x-ui.input-group>
            @if ($usesModifiers)
                {{-- Buka modal daftar Open Bill untuk dibayar --}}
                <button type="button" x-on:click="openBillsModal()"
                    class="inline-flex h-12 shrink-0 items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-800 dark:bg-white/3 dark:text-gray-300 dark:hover:bg-white/5">
                    <svg class="size-5 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" /></svg>
                    <span class="hidden sm:inline">Bayar Open Bill</span>
                    {{-- Hanya angkanya yang berwarna: merah bila ada tagihan, abu bila kosong. --}}
                    <span class="min-w-6 rounded-full px-1.5 py-0.5 text-center text-xs font-semibold tabular-nums"
                        x-bind:class="openBills.length ? 'bg-error-500 text-white' : 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400'"
                        x-text="openBills.length"></span>
                </button>
            @endif
            </div>

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
                        class="relative flex cursor-pointer flex-col gap-1 overflow-hidden rounded-xl border-2 p-3 transition hover:-translate-y-0.5 hover:shadow-theme-md"
                        x-bind:class="[toneFor(item.category_id), qtyOf(item.id) > 0 ? 'border-brand-500' : 'border-transparent', item.stock <= 0 && 'cursor-not-allowed opacity-50 hover:translate-y-0 hover:shadow-none']">
                        {{-- Gambar barang; bila belum ada foto, tampil gambar pengganti --}}
                        <div class="relative mb-2 aspect-[4/3] w-full overflow-hidden rounded-lg bg-white/70 dark:bg-gray-900/60">
                            <template x-if="item.image">
                                <img x-bind:src="item.image" x-bind:alt="item.name" loading="lazy" class="size-full object-cover" />
                            </template>
                            <template x-if="!item.image">
                                <div class="flex size-full flex-col items-center justify-center gap-1 text-gray-300 dark:text-gray-600">
                                    <svg class="size-10 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9" />
                                    </svg>
                                    <span class="text-lg font-bold text-gray-400 dark:text-gray-500" x-text="item.name.split(' ').slice(0, 2).map((w) => w.charAt(0)).join('').toUpperCase()"></span>
                                </div>
                            </template>
                            <span x-show="qtyOf(item.id) > 0" x-text="qtyOf(item.id)"
                                class="absolute end-2 top-2 flex min-w-6 items-center justify-center rounded-full bg-brand-500 px-1.5 py-0.5 text-xs font-bold text-white"></span>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400" x-text="categoryName(item.category_id)"></span>
                        <span class="line-clamp-2 min-h-10 text-sm font-semibold text-gray-800 dark:text-white/90" x-text="item.name"></span>
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
        {{-- Panel setinggi layar. Bila tidak muat (layar pendek / zoom in) seluruh panel bisa di-scroll,
             dan tombol simpan tetap menempel di bawah (sticky) agar selalu terlihat. --}}
        <div class="custom-scrollbar flex min-w-0 flex-col self-start overflow-hidden rounded-2xl border border-gray-200 bg-white xl:sticky xl:top-24 xl:max-h-[calc(100vh-7rem)] xl:overflow-y-auto dark:border-gray-800 dark:bg-white/3">
            <div class="flex shrink-0 flex-col gap-3 border-b border-gray-100 p-5 dark:border-gray-800">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Pesanan Baru</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400" x-text="totalQty ? totalQty + ' barang di keranjang' : 'Klik barang untuk menambah'"></p>
                    </div>
                    <x-ui.button variant="outline" size="icon-sm" aria-label="Kosongkan keranjang" x-show="cart.length" x-on:click="clearCart()" class="text-error-500">
                        <x-svg.trash-bin class="size-5" />
                    </x-ui.button>
                </div>

                {{-- Nama pelanggan sekaligus cari member (nama / nomor HP); "Tambah member" bila belum terdaftar --}}
                <div class="relative" x-on:click.outside="memberOpen = false">
                    {{-- Wadah khusus input: label Member diposisikan terhadap tinggi input saja (bukan + baris HP). --}}
                    <div class="relative">
                        <x-ui.input x-model="customer" x-on:input="onCustomerInput()" x-on:keydown="memberKey($event)"
                            x-on:focus="if (!memberId && customer.trim().length >= 2 && memberResults.length) memberOpen = true"
                            x-bind:readonly="!!activeOrder" placeholder="Nama pelanggan / cari member (opsional)" maxlength="100"
                            autocomplete="off" x-bind:class="memberId && 'pe-28'" />
                        {{-- Member terpilih --}}
                        <div x-show="memberId" x-cloak class="absolute inset-y-0 end-2 flex items-center gap-1.5">
                            <span class="inline-flex h-6 items-center rounded-md bg-success-50 px-2 text-xs leading-none font-medium text-success-700 dark:bg-success-500/15 dark:text-success-500">Member</span>
                            <button type="button" x-on:click="clearMemberSelection(); customer = ''" aria-label="Hapus member"
                                class="flex size-7 items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-white/5">
                                <x-svg.x class="size-4" />
                            </button>
                        </div>
                    </div>
                    <p x-show="memberId && memberPhone" x-cloak class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="'HP ' + memberPhone"></p>

                    {{-- Hasil pencarian member --}}
                    <div x-show="memberOpen && !memberId" x-cloak
                        class="absolute inset-x-0 top-full z-30 mt-1 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
                        <p x-show="memberLoading" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Mencari member…</p>
                        <template x-for="(member, i) in memberResults" :key="member.id">
                            <button type="button" x-on:click="selectMember(member)" x-on:mouseenter="memberIndex = i"
                                class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-start"
                                x-bind:class="memberIndex === i ? 'bg-brand-50 dark:bg-brand-500/10' : ''">
                                <span class="truncate text-sm font-medium text-gray-800 dark:text-white/90" x-text="member.name"></span>
                                <span class="shrink-0 text-xs text-gray-500 tabular-nums dark:text-gray-400" x-text="member.phone_label"></span>
                            </button>
                        </template>
                        <p x-show="!memberLoading && !memberResults.length" class="px-4 pt-3 pb-1 text-sm text-gray-500 dark:text-gray-400">Member tidak ditemukan.</p>
                        <button type="button" x-show="canAddMember" x-on:click="openQuickMember()"
                            class="flex w-full items-center gap-2 border-t border-gray-100 px-4 py-3 text-start text-sm font-medium text-brand-600 hover:bg-gray-50 dark:border-gray-800 dark:text-brand-400 dark:hover:bg-white/5">
                            <x-svg.plus class="size-4" />
                            <span x-text="'Tambah member &quot;' + customer.trim() + '&quot;'"></span>
                        </button>
                    </div>
                </div>

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
                <template x-if="activeOrder">
                    <div class="mb-3 flex items-center justify-between rounded-xl border border-warning-200 bg-warning-50 px-3.5 py-2.5 text-xs text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                        <div>
                            <p class="font-semibold" x-text="'Tambah ke Open Bill ' + activeOrder.code"></p>
                            <p x-text="(activeOrder.table_label || '') + (activeOrder.customer_name ? ' · ' + activeOrder.customer_name : '')"></p>
                        </div>
                        <a href="{{ route('kasir.pos') }}" class="font-medium underline">Batal</a>
                    </div>
                </template>
                <template x-for="(line, index) in lines" :key="line.uid">
                    <div class="flex items-start gap-3 border-b border-dashed border-gray-100 py-3 last:border-0 dark:border-gray-800">
                        <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-400" x-text="index + 1"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90">
                                <span x-text="line.item.name"></span>
                                <small class="text-gray-500 dark:text-gray-400" x-text="' · ' + money(lineUnitPrice(line))"></small>
                            </p>
                            <template x-if="line.modifiers?.length">
                                <p class="mt-0.5 text-xs text-brand-600 dark:text-brand-400" x-text="'+ ' + line.modifiers.map((m) => m.name + (m.price_delta > 0 ? ' (+' + money(m.price_delta) + ')' : '')).join(', ')"></p>
                            </template>
                            <template x-if="line.notes">
                                <p class="mt-0.5 text-xs italic text-gray-500 dark:text-gray-400" x-text="'Catatan: ' + line.notes"></p>
                            </template>
                            <div class="mt-1.5 flex items-center gap-2">
                                <div class="flex w-fit items-center overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                                    <button type="button" class="flex size-7 items-center justify-center bg-gray-50 text-gray-600 dark:bg-white/5 dark:text-gray-400" x-on:click="dec(line.uid)" aria-label="Kurangi">−</button>
                                    <input type="number" min="1" x-bind:max="line.item.stock" x-bind:value="line.qty" x-on:change="setQty(line.uid, $event.target.value)" aria-label="Jumlah"
                                        class="h-7 w-12 border-x border-gray-200 bg-white text-center text-sm text-gray-800 outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90" />
                                    <button type="button" class="flex size-7 items-center justify-center bg-gray-50 text-gray-600 dark:bg-white/5 dark:text-gray-400" x-on:click="addConfiguredLine(line.item, 1, line.modifier_ids, line.notes)" aria-label="Tambah">+</button>
                                </div>
                                <button x-show="usesModifiers" type="button" x-on:click="openModifierModal(line.item, line)"
                                    class="text-xs text-gray-500 hover:text-brand-500 dark:text-gray-400">Opsi/Catatan</button>
                            </div>
                        </div>
                        <div class="text-end">
                            <p class="text-sm font-semibold whitespace-nowrap text-gray-800 dark:text-white/90" x-text="money(lineUnitPrice(line) * line.qty)"></p>
                            <button type="button" class="mt-1 text-xs text-error-500 hover:underline" x-on:click="remove(line.uid)">Hapus</button>
                        </div>
                    </div>
                </template>
                <div x-show="!cart.length" class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    Keranjang kosong.<br><small>Klik barang, atau scan barcode di kolom cari.</small>
                </div>
            </div>

            {{-- Ringkasan & pembayaran --}}
            <div class="flex shrink-0 flex-col gap-4 border-t border-gray-100 bg-gray-50 p-5 dark:border-gray-800 dark:bg-white/2">
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
                        <span x-text="money(subtotal)"></span>
                    </div>
                    <div x-show="discountAmount > 0" class="flex justify-between text-sm text-error-500">
                        <span x-text="'Diskon' + (discountType === 'percent' ? ' (' + discountValue + '%)' : '')"></span>
                        <span x-text="'-' + money(discountAmount)"></span>
                    </div>
                    {{-- Pajak & biaya layanan (modul FNB). Inclusive hanya keterangan, tidak menambah total. --}}
                    <template x-for="d in taxes.details" :key="d.name + d.rate + d.method">
                        <div class="flex justify-between text-sm text-gray-500 dark:text-gray-400">
                            <span x-text="d.name + ' ' + d.rate + '%' + (d.included ? ' (termasuk)' : '')"></span>
                            <span x-bind:class="d.included && 'italic'" x-text="(d.included ? '' : '+') + money(d.amount)"></span>
                        </div>
                    </template>
                    <div class="flex items-center justify-between">
                        <span class="text-base font-semibold text-gray-800 dark:text-white/90">Total</span>
                        <span class="text-2xl font-bold text-gray-800 dark:text-white/90" x-text="money(total)"></span>
                    </div>
                </div>

                <div x-show="!activeOrder">
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

                <div class="flex flex-wrap items-center gap-4">
                    <button type="button" class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400" x-on:click="showNotes = !showNotes"
                        x-text="showNotes ? '− Sembunyikan catatan' : '+ Tambah catatan'"></button>
                    <button x-show="!activeOrder" type="button" class="text-xs text-brand-600 hover:underline dark:text-brand-400" x-on:click="showDiscount = !showDiscount"
                        x-text="showDiscount ? '− Tutup diskon' : '+ Tambah diskon'"></button>
                </div>
                <div x-show="showDiscount && !activeOrder" class="flex items-center gap-2">
                    <select x-model="discountType" class="h-10 rounded-lg border border-gray-300 bg-white px-2 text-xs dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                        <option value="percent">Persen (%)</option>
                        <option value="fixed">Nominal (Rp)</option>
                    </select>
                    <x-ui.input type="number" x-model="discountValue" min="0" placeholder="Nilai diskon" class="h-10 flex-1" />
                </div>
                <x-ui.textarea x-show="showNotes" x-model="notes" rows="2" maxlength="500" placeholder="Catatan transaksi" class="min-h-16" />

                {{-- Tombol simpan: sticky di dasar panel (bg solid agar isi di belakangnya tidak tembus). --}}
                <div class="sticky bottom-0 z-1 -mx-5 -mb-5 flex flex-col gap-4 border-t border-gray-100 bg-gray-50 px-5 pt-4 pb-5 dark:border-gray-800 dark:bg-gray-900">
                <template x-if="activeOrder">
                    <x-ui.button size="lg" class="h-12 w-full text-base" x-bind:disabled="saving || !cart.length" x-on:click="submitAddItems()">
                        <span x-text="saving ? 'Menyimpan…' : 'Tambahkan ke Open Bill'"></span>
                    </x-ui.button>
                </template>
                <template x-if="!activeOrder">
                    <div class="flex gap-2">
                        <x-ui.button x-show="usesModifiers" type="button" variant="outline" size="lg" class="h-12 flex-1 text-sm"
                            x-bind:disabled="!canSaveOpenBill" x-on:click="submit(true)">
                            <span>Simpan Open Bill</span>
                        </x-ui.button>
                        <x-ui.button size="lg" class="h-12 flex-1 text-base" x-bind:disabled="!canSubmit" x-on:click="submit(false)">
                            <span x-text="saving ? 'Menyimpan…' : 'Bayar & Simpan'"></span>
                        </x-ui.button>
                    </div>
                </template>
                <p x-show="shiftRequired && !activeOrder" x-cloak class="-mt-2 text-center text-xs text-error-500">
                    Buka shift kasir dulu untuk menyimpan transaksi · <a href="{{ route('kasir.shift.index') }}" class="font-medium underline">Buka Shift</a>
                </p>
                <p x-show="!activeOrder && needsTable && cart.length && !selectedTable" class="-mt-2 text-center text-xs text-error-500">Pilih meja dulu · tekan Simpan untuk membuka daftar meja</p>
                <p class="-mt-2 text-center text-xs text-gray-400">Ctrl+Enter untuk simpan</p>
                </div>
            </div>
        </div>

        @if ($usesModifiers)
            {{-- ================= Modal Daftar Open Bill (kartu besar, sekali tekan → bayar) ================= --}}
            <x-common.modal show="billsOpen" close="billsOpen = false" max-width="4xl">
                <x-slot:heading>
                    <span>Bayar Open Bill</span>
                    <span class="ms-1 text-gray-400" x-text="'(' + openBills.length + ')'"></span>
                </x-slot:heading>

                <div class="space-y-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            <span x-show="openBills.length">Tekan tagihan untuk membayar · total belum dibayar <strong class="text-gray-800 dark:text-white/90" x-text="money(openBillsTotal)"></strong></span>
                            <span x-show="!openBills.length">Tidak ada tagihan yang belum dibayar.</span>
                        </p>
                        <x-ui.input-group x-show="openBills.length > 6" class="h-11 sm:w-72">
                            <x-ui.input-group.addon align="inline-start">
                                <x-svg.search class="size-5 text-gray-400" />
                            </x-ui.input-group.addon>
                            <x-ui.input-group.input type="text" x-model="billSearch" placeholder="Cari meja / pelanggan" autocomplete="off" />
                        </x-ui.input-group>
                    </div>

                    <div class="custom-scrollbar grid max-h-[65vh] grid-cols-1 gap-4 overflow-y-auto p-1 sm:grid-cols-2 lg:grid-cols-3">
                        <template x-for="bill in filteredBills" :key="bill.id">
                            <div class="flex flex-col overflow-hidden rounded-2xl border-2 border-gray-200 bg-white transition hover:border-brand-400 hover:shadow-theme-md dark:border-gray-700 dark:bg-gray-900">
                                {{-- Seluruh kartu = tombol bayar (target sentuh besar) --}}
                                <button type="button" x-on:click="payBill(bill)" class="flex flex-1 flex-col gap-4 p-5 text-start focus-visible:outline-hidden focus-visible:ring-3 focus-visible:ring-brand-500/30">
                                    <div class="flex items-center gap-4">
                                        <span class="flex size-16 shrink-0 items-center justify-center rounded-2xl bg-warning-100 text-xl font-bold text-warning-700 dark:bg-warning-500/20 dark:text-warning-300"
                                            x-text="bill.table ? bill.table.replace('Meja ', '').split(' · ')[0] : 'TA'"></span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-lg font-semibold text-gray-800 dark:text-white/90" x-text="bill.customer"></p>
                                            <p class="truncate text-sm text-gray-500 dark:text-gray-400" x-text="(bill.table ?? bill.order ?? 'Tanpa meja') + ' · ' + bill.time"></p>
                                        </div>
                                    </div>
                                    <div class="flex items-end justify-between gap-3">
                                        <div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400" x-text="bill.items + ' item · #' + bill.no"></p>
                                            <p x-show="bill.remaining < bill.total" class="text-xs text-gray-400" x-text="'dari ' + money(bill.total)"></p>
                                        </div>
                                        <p class="text-2xl font-bold text-gray-800 tabular-nums dark:text-white/90" x-text="money(bill.remaining)"></p>
                                    </div>
                                    <span class="flex h-12 w-full items-center justify-center rounded-xl bg-brand-500 text-base font-semibold text-white">Bayar</span>
                                </button>
                                <a x-bind:href="@js(route('kasir.pos')) + '?order=' + bill.id"
                                    class="border-t border-gray-100 py-3 text-center text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-brand-600 dark:border-gray-800 dark:text-gray-300 dark:hover:bg-white/5">+ Tambah Menu</a>
                            </div>
                        </template>
                    </div>

                    <p x-show="openBills.length && !filteredBills.length" x-cloak class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">Tagihan tidak ditemukan.</p>
                </div>
            </x-common.modal>
        @endif

        {{-- ================= Modal Tambah Member cepat (dari kolom nama pelanggan) ================= --}}
        <x-common.modal show="quickMember.open" close="if (!quickMember.saving) quickMember.open = false" max-width="md" title="Tambah Member">
            <form class="grid grid-cols-1 gap-5" x-on:submit.prevent="saveQuickMember()">
                <div class="flex flex-col gap-1.5">
                    <label for="qm-name" class="text-sm font-medium text-gray-700 dark:text-gray-400">Nama Member<span class="text-error-500">*</span></label>
                    <x-ui.input id="qm-name" x-model="quickMember.name" maxlength="100" placeholder="mis. Budi Santoso" required autocomplete="off" />
                    <p x-show="quickMember.errors.name" x-text="quickMember.errors.name" class="text-sm text-error-500"></p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="qm-phone" class="text-sm font-medium text-gray-700 dark:text-gray-400">Nomor Telepon<span class="text-error-500">*</span></label>
                    <x-ui.input id="qm-phone" type="tel" inputmode="tel" x-model="quickMember.phone" maxlength="20" placeholder="08xx xxxx xxxx" required autocomplete="off" />
                    <p x-show="quickMember.errors.phone" x-text="quickMember.errors.phone" class="text-sm text-error-500"></p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="qm-birth" class="text-sm font-medium text-gray-700 dark:text-gray-400">Tanggal Lahir<span class="text-error-500">*</span></label>
                    <x-ui.input id="qm-birth" type="date" x-model="quickMember.birth_date" max="{{ now()->format('Y-m-d') }}" required />
                    <p x-show="quickMember.errors.birth_date" x-text="quickMember.errors.birth_date" class="text-sm text-error-500"></p>
                </div>
                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" variant="outline" x-on:click="quickMember.open = false" x-bind:disabled="quickMember.saving">Batal</x-ui.button>
                    <x-ui.button type="submit" class="min-w-40" x-bind:disabled="quickMember.saving">
                        <x-svg.spinner class="size-4" x-show="quickMember.saving" x-cloak />
                        <span x-text="quickMember.saving ? 'Menyimpan…' : 'Simpan & Pilih'">Simpan &amp; Pilih</span>
                    </x-ui.button>
                </div>
            </form>
        </x-common.modal>

        {{-- ================= Modal Modifier & Catatan Menu ================= --}}
        <x-common.modal show="modModal.open" max-width="lg" title="Opsi & Catatan Menu">
            <div x-show="modModal.item" class="space-y-5">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white/90" x-text="modModal.item?.name"></h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400" x-text="modModal.item ? money(price(modModal.item)) : ''"></p>
                    </div>
                </div>

                <template x-for="group in (modModal.item?.modifier_groups ?? [])" :key="group.id">
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="group.name"></span>
                            <span class="text-xs text-gray-500 dark:text-gray-400"
                                x-text="(group.is_required ? 'Wajib · ' : 'Opsional · ') + (group.max_select === 1 ? 'Pilih 1' : 'Maks ' + group.max_select)"></span>
                        </div>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <template x-for="mod in group.modifiers" :key="mod.id">
                                <button type="button" x-on:click="toggleMod(group, mod)"
                                    class="flex items-center justify-between rounded-xl border-2 px-3 py-2 text-start text-sm transition"
                                    x-bind:class="isModSelected(mod.id)
                                        ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300'
                                        : 'border-gray-200 text-gray-700 hover:border-gray-300 dark:border-gray-800 dark:text-gray-300'">
                                    <span x-text="mod.name"></span>
                                    <span class="text-xs font-medium" x-text="mod.price_delta > 0 ? '+' + money(mod.price_delta) : 'Gratis'"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan Dapur (Opsional)</label>
                    <x-ui.input type="text" x-model="modModal.notes" maxlength="255" placeholder="mis. Tidak pedas, es terpisah" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" variant="outline" x-on:click="modModal.open = false">Batal</x-ui.button>
                    <x-ui.button type="button" x-on:click="confirmModifierModal()">Simpan ke Keranjang</x-ui.button>
                </div>
            </div>
        </x-common.modal>

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

    {{-- Modal Bayar Tagihan (bersama halaman Meja) --}}
    @if ($usesModifiers)
        <x-main.kasir.pay-bill-modal />
    @endif
@endsection

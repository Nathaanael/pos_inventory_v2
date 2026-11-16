@extends('layouts.kasir')

{{-- Pantauan meja (dulu App\Filament\Kasir\Pages\Meja). Data diperbarui otomatis tiap 20 detik. --}}
@section('content')
    <div x-data="{
            tables: @js($tables),
            search: '',
            status: 'all',
            now: Date.now(),
            detail: null,
            init() {
                setInterval(() => this.now = Date.now(), 30000);
                setInterval(() => this.refresh(), 20000);
            },
            async refresh() {
                try {
                    const response = await fetch(@js(route('kasir.meja.data')), { headers: { Accept: 'application/json' } });
                    if (response.ok) this.tables = await response.json();
                } catch (e) {}
            },
            get busy() { return this.tables.filter((t) => t.occupied).length; },
            get free() { return this.tables.length - this.busy; },
            match(t) {
                const q = this.search.trim().toLowerCase();
                const okQ = !q || t.number.toLowerCase().includes(q) || (t.name ?? '').toLowerCase().includes(q) || (t.customer ?? '').toLowerCase().includes(q);
                const okS = this.status === 'all' || (this.status === 'busy' ? t.occupied : !t.occupied);
                return okQ && okS;
            },
            duration(since) {
                if (!since) return '';
                const m = Math.max(0, Math.floor((this.now - new Date(since).getTime()) / 60000));
                return Math.floor(m / 60) + ':' + String(m % 60).padStart(2, '0');
            },
            money(v) { return 'Rp' + new Intl.NumberFormat('id-ID').format(Math.round(v || 0)); },
            open(t) {
                if (t.occupied) this.detail = t;
                else window.location.href = @js(route('kasir.pos')) + '?meja=' + t.id;
            },
        }">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Meja</h2>
            <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                <span class="flex items-center gap-2"><i class="size-2.5 rounded-full bg-success-500"></i><span x-text="free + ' kosong'"></span></span>
                <span class="flex items-center gap-2"><i class="size-2.5 rounded-full bg-warning-500"></i><span x-text="busy + ' terisi'"></span></span>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-800 dark:bg-white/3">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <x-ui.input-group class="h-11 sm:w-80">
                    <x-ui.input-group.addon align="inline-start">
                        <x-svg.search class="size-5 text-gray-400" />
                    </x-ui.input-group.addon>
                    <x-ui.input-group.input type="text" x-model="search" placeholder="Cari meja atau nama pelanggan" />
                </x-ui.input-group>
                <div class="flex gap-1 rounded-xl bg-gray-100 p-1 sm:w-72 dark:bg-white/5">
                    @foreach (['all' => 'Semua', 'free' => 'Kosong', 'busy' => 'Terisi'] as $key => $label)
                        <button type="button" x-on:click="status = @js($key)"
                            class="flex h-9 flex-1 items-center justify-center rounded-lg text-sm font-medium transition-colors"
                            x-bind:class="status === @js($key) ? 'bg-white text-gray-800 shadow-theme-xs dark:bg-gray-800 dark:text-white/90' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            <p x-show="!tables.length" class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                Belum ada meja aktif. Minta admin menambahkan meja di menu <strong>Master Meja</strong>.
            </p>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
                <template x-for="t in tables" :key="t.id">
                    <button type="button" x-show="match(t)" x-on:click="open(t)"
                        x-bind:title="t.occupied ? 'Lihat pesanan meja ini' : 'Buat pesanan untuk meja ini'"
                        class="flex min-h-36 flex-col items-start rounded-2xl border-2 p-4 text-start transition hover:-translate-y-0.5 hover:shadow-theme-md"
                        x-bind:class="t.occupied ? 'border-warning-300 bg-warning-50 dark:border-warning-500/40 dark:bg-warning-500/10' : 'border-success-200 bg-success-50 dark:border-success-500/30 dark:bg-success-500/10'">
                        <span class="text-3xl font-bold text-gray-800 dark:text-white/90" x-text="t.number"></span>
                        <span x-show="t.name" class="text-sm text-gray-500 dark:text-gray-400" x-text="t.name"></span>
                        <template x-if="t.occupied">
                            <span class="mt-auto flex flex-wrap gap-1.5 pt-3">
                                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-900 dark:text-gray-300" x-text="money(t.total)"></span>
                                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-900 dark:text-gray-300" x-text="'⏱ ' + duration(t.since)"></span>
                                <span x-show="t.capacity" class="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-900 dark:text-gray-300" x-text="t.capacity + ' kursi'"></span>
                            </span>
                        </template>
                        <span x-show="!t.occupied" class="mt-auto pt-3 text-xs font-medium text-success-600 dark:text-success-500"
                            x-text="'Kosong' + (t.capacity ? ' · ' + t.capacity + ' kursi' : '')"></span>
                    </button>
                </template>
            </div>
        </div>

        {{-- Detail meja terisi --}}
        <x-common.modal show="detail" close="detail = null" max-width="md">
            <template x-if="detail">
                <div>
                    <h3 class="pe-12 text-xl font-semibold text-gray-800 dark:text-white/90" x-text="'Meja ' + detail.number + (detail.name ? ' · ' + detail.name : '')"></h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="'Terisi sejak ' + (detail.orders[0]?.time ?? '-') + ' · ' + duration(detail.since) + ' jam'"></p>

                    <div class="mt-5 divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                        <template x-for="o in detail.orders" :key="o.code">
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="o.customer || 'Pelanggan umum'"></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400" x-text="o.code + ' · ' + o.time"></p>
                                </div>
                                <strong class="text-sm text-gray-800 dark:text-white/90" x-text="money(o.total)"></strong>
                            </div>
                        </template>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-base font-semibold text-gray-800 dark:text-white/90">Total</span>
                        <span class="text-xl font-bold text-gray-800 dark:text-white/90" x-text="money(detail.total)"></span>
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <x-ui.button variant="outline" x-on:click="detail = null">Tutup</x-ui.button>
                        <x-ui.button as="a" variant="outline" x-bind:href="{{ \Illuminate\Support\Js::from(route('kasir.pos')) }} + '?meja=' + detail.id">Tambah Pesanan</x-ui.button>
                        <form method="POST" x-bind:action="{{ \Illuminate\Support\Js::from(route('kasir.meja.release', '__ID__')) }}.replace('__ID__', detail.id)">
                            @csrf
                            <x-ui.button type="submit" class="w-full bg-success-500 hover:bg-success-600">Kosongkan</x-ui.button>
                        </form>
                    </div>
                </div>
            </template>
        </x-common.modal>
    </div>
@endsection

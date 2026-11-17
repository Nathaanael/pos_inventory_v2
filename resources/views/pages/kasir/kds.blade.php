@extends('layouts.kasir')

@section('content')
    <div x-data="{
            tickets: @js($tickets),
            now: Date.now(),
            init() {
                setInterval(() => this.now = Date.now(), 15000);
                setInterval(() => this.refresh(), 10000);
            },
            async refresh() {
                try {
                    const res = await fetch(@js(route('kasir.kds.data')), { headers: { Accept: 'application/json' } });
                    if (res.ok) this.tickets = await res.json();
                } catch (e) {}
            },
            elapsedMin(iso) {
                return Math.max(0, Math.floor((this.now - new Date(iso).getTime()) / 60000));
            },
            badgeClass(min) {
                if (min >= 15) return 'bg-error-100 text-error-700 dark:bg-error-500/20 dark:text-error-300';
                if (min >= 8) return 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300';
                return 'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-300';
            },
            nextStatus(s) {
                return { queued: 'preparing', preparing: 'ready', ready: 'served' }[s] ?? 'served';
            },
            nextLabel(s) {
                return { queued: 'Mulai Masak', preparing: 'Siap Saji', ready: 'Selesai / Antar' }[s] ?? 'Selesai';
            },
            statusLabel(s) {
                return { queued: 'Antri', preparing: 'Dimasak', ready: 'Siap', served: 'Diantar' }[s] ?? s;
            },
            async advance(item) {
                const url = @js(route('kasir.kds.item-status', '__ID__')).replace('__ID__', item.id);
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ kitchen_status: this.nextStatus(item.kitchen_status) }),
                });
                const data = await res.json().catch(() => null);
                if (data?.ok && data.tickets) this.tickets = data.tickets;
            },
        }">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Kitchen Display System (KDS)</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Daftar pesanan aktif untuk dapur & bar (diperbarui otomatis tiap 10 detik).</p>
            </div>
            <x-ui.button type="button" variant="outline" x-on:click="refresh()">Muat Ulang</x-ui.button>
        </div>

        <div x-show="!tickets.length" class="rounded-2xl border border-gray-200 bg-white py-16 text-center text-sm text-gray-400 dark:border-gray-800 dark:bg-white/3">
            Tidak ada antrian pesanan di dapur saat ini.
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <template x-for="t in tickets" :key="t.id">
                <div class="flex flex-col rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/3">
                    <div class="flex items-start justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-800">
                        <div>
                            <h3 class="text-base font-bold text-gray-800 dark:text-white/90" x-text="t.order_label"></h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="t.code + (t.customer ? ' · ' + t.customer : '') + ' · ' + t.time"></p>
                        </div>
                        <span class="rounded-lg px-2.5 py-1 text-xs font-semibold"
                            x-bind:class="badgeClass(elapsedMin(t.created_at))"
                            x-text="elapsedMin(t.created_at) + ' mnt'"></span>
                    </div>

                    <div class="mt-3 flex-1 divide-y divide-dashed divide-gray-100 dark:divide-gray-800">
                        <template x-for="item in t.items" :key="item.id">
                            <div class="flex items-center justify-between gap-3 py-2.5">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 dark:text-white/90" x-text="item.qty + 'x ' + item.name"></p>
                                    <p x-show="item.modifiers?.length" class="text-xs text-brand-600 dark:text-brand-400" x-text="'+ ' + item.modifiers.join(', ')"></p>
                                    <p x-show="item.notes" class="text-xs italic text-gray-500 dark:text-gray-400" x-text="'Catatan: ' + item.notes"></p>
                                </div>
                                <button type="button" x-on:click="advance(item)"
                                    class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                    x-bind:class="{
                                        'bg-gray-100 text-gray-700 hover:bg-brand-500 hover:text-white dark:bg-white/10 dark:text-gray-200': item.kitchen_status === 'queued',
                                        'bg-warning-500 text-white hover:bg-warning-600': item.kitchen_status === 'preparing',
                                        'bg-success-500 text-white hover:bg-success-600': item.kitchen_status === 'ready',
                                    }"
                                    x-text="nextLabel(item.kitchen_status)"></button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>
@endsection

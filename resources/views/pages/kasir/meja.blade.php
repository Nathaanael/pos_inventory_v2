@extends('layouts.kasir')

{{--
    Pantauan meja (dulu App\Filament\Kasir\Pages\Meja). Data diperbarui otomatis tiap 20 detik.
    Tampilan denah: tiap meja digambar dengan kursi di keempat sisi.
      oranye = terisi (tagihan, lama duduk, kursi) · hijau = kosong.
    Meja besar (> 6 kursi) dibuat memanjang dengan 3 kursi di kiri & kanan.
--}}
@section('content')
    <div x-data="{
            tables: @js($tables),
            search: '',
            status: 'all',
            now: Date.now(),
            detail: null,
            payTarget: null,
            payMethod: 'tunai',
            payAmount: '',
            paying: false,
            payError: '',
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
            get visible() { return this.tables.filter((t) => this.match(t)); },
            match(t) {
                const q = this.search.trim().toLowerCase();
                const okQ = !q || t.number.toLowerCase().includes(q) || (t.name ?? '').toLowerCase().includes(q) || (t.customer ?? '').toLowerCase().includes(q);
                const okS = this.status === 'all' || (this.status === 'busy' ? t.occupied : !t.occupied);
                return okQ && okS;
            },
            isLong(t) { return (t.capacity ?? 0) > 6; },
            sideChairs(t) { return this.isLong(t) ? 3 : 1; },
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
            openPay(o) {
                this.payTarget = o;
                this.payMethod = 'tunai';
                this.payAmount = String(Math.round(o.remaining || o.total));
                this.payError = '';
            },
            async submitPay() {
                if (!this.payTarget || this.paying) return;
                this.paying = true;
                this.payError = '';
                try {
                    const url = @js(route('kasir.orders.pay', '__ID__')).replace('__ID__', this.payTarget.id);
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({
                            payment_method: this.payMethod,
                            paid_amount: this.payMethod === 'tunai' ? Number(this.payAmount || 0) : null,
                        }),
                    });
                    const data = await res.json().catch(() => null);
                    if (data?.ok) {
                        if (data.tables) {
                            this.tables = data.tables;
                            this.detail = this.tables.find((t) => t.id === this.detail?.id) ?? null;
                        }
                        this.payTarget = null;
                    } else {
                        this.payError = data?.message ?? 'Gagal memproses pembayaran.';
                    }
                } catch (e) {
                    this.payError = 'Koneksi gagal. Coba lagi.';
                } finally {
                    this.paying = false;
                }
            },
        }">
        {{-- Judul + tab --}}
        <div class="mb-6 border-b border-gray-200 dark:border-gray-800">
            <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">Meja</h2>
            <nav class="mt-4 flex gap-6 text-sm">
                <span class="-mb-px border-b-2 border-brand-500 pb-3 font-medium text-gray-800 dark:text-white/90">Status Real-time</span>
                @if (auth()->user()?->isAdmin())
                    <a href="{{ route('meja.index') }}" class="-mb-px border-b-2 border-transparent pb-3 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">Konfigurasi Meja</a>
                @endif
            </nav>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 dark:border-gray-800 dark:bg-white/3">
            {{-- Toolbar --}}
            <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <x-ui.input-group class="h-10 sm:w-72">
                    <x-ui.input-group.addon align="inline-start">
                        <x-svg.search class="size-5 text-gray-400" />
                    </x-ui.input-group.addon>
                    <x-ui.input-group.input type="text" x-model="search" placeholder="Cari meja atau nama pelanggan" />
                </x-ui.input-group>

                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                        <span class="flex items-center gap-2"><i class="size-2.5 rounded-full bg-success-400"></i><span x-text="free + ' kosong'"></span></span>
                        <span class="flex items-center gap-2"><i class="size-2.5 rounded-full bg-orange-400"></i><span x-text="busy + ' terisi'"></span></span>
                    </div>
                    <x-common.form.select class="h-10 w-40" x-model="status" aria-label="Filter status"
                        :options="['all' => 'Semua Status', 'free' => 'Kosong', 'busy' => 'Terisi']" />
                </div>
            </div>

            <p x-show="!tables.length" class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                Belum ada meja aktif. Minta admin menambahkan meja di menu <strong>Master Meja</strong>.
            </p>
            <p x-show="tables.length && !visible.length" x-cloak class="py-12 text-center text-sm text-gray-500 dark:text-gray-400">Meja tidak ditemukan.</p>

            {{-- Denah meja --}}
            <div class="grid grid-flow-row-dense grid-cols-2 gap-x-10 gap-y-12 px-3 py-3 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
                <template x-for="t in visible" :key="t.id">
                    <div class="relative flex justify-center" x-bind:class="isLong(t) && 'row-span-2'">
                        <button type="button" x-on:click="open(t)"
                            x-bind:title="t.occupied ? 'Lihat pesanan meja ini' : 'Buat pesanan untuk meja ini'"
                            class="group relative w-full max-w-36 transition hover:-translate-y-0.5"
                            x-bind:class="isLong(t) ? 'h-full min-h-72' : 'aspect-square'">

                            {{-- Kursi atas & bawah --}}
                            <span class="absolute -top-2.5 left-1/2 h-2 w-12 -translate-x-1/2 rounded-t-md"
                                x-bind:class="t.occupied ? 'bg-orange-100 dark:bg-orange-500/20' : 'bg-success-100 dark:bg-success-500/20'"></span>
                            <span class="absolute -bottom-2.5 left-1/2 h-2 w-12 -translate-x-1/2 rounded-b-md"
                                x-bind:class="t.occupied ? 'bg-orange-100 dark:bg-orange-500/20' : 'bg-success-100 dark:bg-success-500/20'"></span>

                            {{-- Kursi kiri & kanan (3 kursi untuk meja panjang) --}}
                            <span class="absolute inset-y-0 -left-2.5 flex flex-col justify-around py-4">
                                <template x-for="n in sideChairs(t)" :key="n">
                                    <span class="h-12 w-2 rounded-l-md" x-bind:class="t.occupied ? 'bg-orange-100 dark:bg-orange-500/20' : 'bg-success-100 dark:bg-success-500/20'"></span>
                                </template>
                            </span>
                            <span class="absolute inset-y-0 -right-2.5 flex flex-col justify-around py-4">
                                <template x-for="n in sideChairs(t)" :key="n">
                                    <span class="h-12 w-2 rounded-r-md" x-bind:class="t.occupied ? 'bg-orange-100 dark:bg-orange-500/20' : 'bg-success-100 dark:bg-success-500/20'"></span>
                                </template>
                            </span>

                            {{-- Meja --}}
                            <span class="absolute inset-0 flex flex-col items-start justify-between rounded-xl p-3.5 text-start transition group-hover:shadow-theme-md"
                                x-bind:class="t.occupied ? 'bg-orange-50 dark:bg-orange-500/15' : 'bg-success-50 dark:bg-success-500/15'">
                                <span>
                                    <span class="block text-lg font-semibold text-gray-800 dark:text-white/90" x-text="t.number"></span>
                                    <span x-show="t.name" class="block text-xs text-gray-500 dark:text-gray-400" x-text="t.name"></span>
                                </span>

                                {{-- Info meja terisi --}}
                                <template x-if="t.occupied">
                                    <span class="flex flex-col items-start gap-1.5">
                                        <span class="inline-flex items-center gap-1 rounded-md bg-orange-100 px-1.5 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-500/20 dark:text-orange-300">
                                            <svg class="size-3.5 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                                            <span x-text="money(t.total)"></span>
                                        </span>
                                        <span class="flex flex-wrap gap-1.5">
                                            <span class="inline-flex items-center gap-1 rounded-md bg-orange-100 px-1.5 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-500/20 dark:text-orange-300">
                                                <x-svg.time class="size-3.5" /> <span x-text="duration(t.since)"></span>
                                            </span>
                                            <span x-show="t.capacity" class="inline-flex items-center gap-1 rounded-md bg-orange-100 px-1.5 py-0.5 text-xs font-medium text-orange-700 dark:bg-orange-500/20 dark:text-orange-300">
                                                <x-svg.user class="size-3.5" /> <span x-text="t.capacity"></span>
                                            </span>
                                        </span>
                                    </span>
                                </template>

                                {{-- Info meja kosong --}}
                                <span x-show="!t.occupied" class="text-xs font-medium text-success-600 dark:text-success-500"
                                    x-text="'Kosong' + (t.capacity ? ' · ' + t.capacity + ' kursi' : '')"></span>
                            </span>
                        </button>
                    </div>
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
                            <div class="px-4 py-3 space-y-2">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <p class="text-sm font-medium text-gray-800 dark:text-white/90" x-text="o.customer || 'Pelanggan umum'"></p>
                                            <span x-show="o.is_open" class="rounded-md bg-warning-100 px-1.5 py-0.5 text-[11px] font-medium text-warning-700 dark:bg-warning-500/20 dark:text-warning-300">Open Bill</span>
                                            <span x-show="!o.is_open" class="rounded-md bg-success-100 px-1.5 py-0.5 text-[11px] font-medium text-success-700 dark:bg-success-500/20 dark:text-success-300">Lunas</span>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400" x-text="o.code + ' · ' + o.time"></p>
                                    </div>
                                    <strong class="text-sm text-gray-800 dark:text-white/90" x-text="money(o.total)"></strong>
                                </div>
                                <template x-if="o.items?.length">
                                    <ul class="space-y-1 border-t border-dashed border-gray-100 pt-2 text-xs text-gray-600 dark:border-gray-800 dark:text-gray-400">
                                        <template x-for="item in o.items" :key="item.id">
                                            <li class="flex justify-between gap-2">
                                                <span>
                                                    <span x-text="item.qty + 'x ' + item.name"></span>
                                                    <span x-show="item.modifiers?.length" class="text-brand-600 dark:text-brand-400" x-text="' (' + item.modifiers.join(', ') + ')'"></span>
                                                    <span x-show="item.notes" class="italic text-gray-400" x-text="' · ' + item.notes"></span>
                                                </span>
                                                <span x-text="money(item.subtotal)"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </template>
                                <div class="flex flex-wrap gap-2 pt-1">
                                    <a x-show="o.is_open" x-bind:href="{{ \Illuminate\Support\Js::from(route('kasir.pos')) }} + '?order=' + o.id"
                                        class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:border-brand-300 hover:text-brand-600 dark:border-gray-700 dark:text-gray-300">
                                        + Tambah Menu
                                    </a>
                                    <button x-show="o.is_open" type="button" x-on:click="openPay(o)"
                                        class="rounded-lg bg-brand-500 px-2.5 py-1 text-xs font-medium text-white hover:bg-brand-600">
                                        Bayar Tagihan
                                    </button>
                                    <form method="POST" x-bind:action="{{ \Illuminate\Support\Js::from(route('kasir.orders.print-kot', '__ID__')) }}.replace('__ID__', o.id)">
                                        @csrf
                                        <button type="submit"
                                            class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:border-gray-300 dark:border-gray-700 dark:text-gray-300">
                                            Cetak KOT
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-base font-semibold text-gray-800 dark:text-white/90">Total</span>
                        <span class="text-xl font-bold text-gray-800 dark:text-white/90" x-text="money(detail.total)"></span>
                    </div>

                    <form method="POST" x-show="tables.some((t) => !t.occupied && t.id !== detail.id)"
                        x-bind:action="{{ \Illuminate\Support\Js::from(route('kasir.meja.move', '__ID__')) }}.replace('__ID__', detail.id)"
                        class="mt-4 flex items-center gap-2 rounded-xl border border-gray-200 p-2.5 dark:border-gray-800">
                        @csrf
                        <select name="target_table_id" required
                            class="h-9 flex-1 rounded-lg border border-gray-300 bg-transparent px-2 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                            <option value="">Pindah ke meja kosong...</option>
                            <template x-for="ft in tables.filter((t) => !t.occupied && t.id !== detail.id)" :key="ft.id">
                                <option x-bind:value="ft.id" x-text="'Meja ' + ft.number + (ft.name ? ' · ' + ft.name : '')"></option>
                            </template>
                        </select>
                        <x-ui.button type="submit" size="sm" variant="outline">Pindah</x-ui.button>
                    </form>

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

        {{-- Modal Bayar Open Bill --}}
        <x-common.modal show="payTarget" close="payTarget = null" max-width="sm" title="Bayar Tagihan Meja">
            <template x-if="payTarget">
                <div class="space-y-4">
                    <div class="rounded-xl bg-gray-50 p-3 text-sm dark:bg-white/5">
                        <p class="font-medium text-gray-800 dark:text-white/90" x-text="payTarget.code"></p>
                        <p class="mt-1 text-lg font-bold text-brand-600 dark:text-brand-400" x-text="money(payTarget.remaining)"></p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-500 uppercase">Metode Pembayaran</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (\App\Models\Outbound::PAYMENT_METHODS as $key => $label)
                                <button type="button" x-on:click="payMethod = @js($key)"
                                    class="rounded-lg border px-2 py-2 text-xs font-medium transition"
                                    x-bind:class="payMethod === @js($key) ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' : 'border-gray-200 text-gray-700 dark:border-gray-700 dark:text-gray-300'">
                                    {{ ['tunai' => 'Tunai', 'debit' => 'Debit', 'qris' => 'QRIS'][$key] ?? $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="payMethod === 'tunai'">
                        <label class="mb-1.5 block text-xs font-medium text-gray-500 uppercase">Uang Diterima</label>
                        <x-ui.input type="number" x-model="payAmount" min="0" />
                    </div>

                    <p x-show="payError" x-text="payError" class="text-xs text-error-500"></p>

                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button type="button" variant="outline" x-on:click="payTarget = null">Batal</x-ui.button>
                        <x-ui.button type="button" x-bind:disabled="paying" x-on:click="submitPay()">
                            <span x-text="paying ? 'Memproses…' : 'Lunasi Tagihan'"></span>
                        </x-ui.button>
                    </div>
                </div>
            </template>
        </x-common.modal>
    </div>
@endsection

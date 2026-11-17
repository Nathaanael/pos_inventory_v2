{{--
    Modal "Bayar Tagihan" Open Bill — dipasang sekali per halaman (Meja, POS).
    Dibuka lewat: $dispatch('pay-bill', { id, code, remaining, total, customer, table })
    Setelah lunas memancarkan 'bill-paid' (window) dengan respons server. Logika: resources/js/components/kasir/pay-bill.js
--}}
@props([])

@php
    $methodLabels = ['tunai' => 'Tunai', 'debit' => 'Debit', 'qris' => 'QRIS'];
@endphp

<div x-data="payBill({
        payUrlTemplate: @js(route('kasir.orders.pay', '__ID__')),
    })"
    x-on:pay-bill.window="open($event.detail)">
    <x-common.modal show="bill" close="close()" max-width="md" title="Bayar Tagihan">
        <template x-if="bill">
            <div class="space-y-5">
                {{-- Ringkasan tagihan --}}
                <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-white/90" x-text="bill.customer || 'Pelanggan umum'"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400" x-text="[bill.table, bill.code].filter(Boolean).join(' · ')"></p>
                        </div>
                        <span class="shrink-0 rounded-md bg-warning-100 px-1.5 py-0.5 text-[11px] font-medium text-warning-700 dark:bg-warning-500/20 dark:text-warning-300">Open Bill</span>
                    </div>
                    <p class="mt-3 text-xs text-gray-500 uppercase dark:text-gray-400">Sisa Tagihan</p>
                    <p class="text-2xl font-bold text-brand-600 tabular-nums dark:text-brand-400" x-text="money(remaining)"></p>
                </div>

                {{-- Metode --}}
                <div>
                    <p class="mb-1.5 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Metode Pembayaran</p>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach (\App\Models\Outbound::PAYMENT_METHODS as $key => $label)
                            <button type="button" x-on:click="method = @js($key)"
                                class="h-10 rounded-lg border px-2 text-sm font-medium transition"
                                x-bind:class="method === @js($key) ? 'border-brand-500 bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300' : 'border-gray-200 text-gray-700 hover:border-gray-300 dark:border-gray-700 dark:text-gray-300'">
                                {{ $methodLabels[$key] ?? $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Tunai: uang diterima (bertitik) + kembalian --}}
                <div x-show="method === 'tunai'" class="space-y-3">
                    <x-common.form.field label="Uang Diterima" for="pay-bill-amount">
                        <x-common.form.money id="pay-bill-amount" model="amount" x-on:keydown.enter.prevent="pay()" />
                    </x-common.form.field>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="value in quickAmounts" :key="value">
                            <button type="button" x-on:click="amount = String(value)" x-text="value === remaining ? 'Uang pas' : money(value)"
                                class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:border-brand-300 hover:text-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"></button>
                        </template>
                    </div>
                    <div x-show="paidNumber > 0" class="flex items-center justify-between rounded-lg px-4 py-3"
                        x-bind:class="change < 0 ? 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500' : 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-500'">
                        <span class="text-sm font-medium" x-text="change < 0 ? 'Uang kurang' : 'Kembalian'"></span>
                        <strong class="text-xl tabular-nums" x-text="money(Math.abs(change))"></strong>
                    </div>
                </div>

                <p x-show="method !== 'tunai'" class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    Nominal dibayar sesuai sisa tagihan: <strong x-text="money(remaining)"></strong>
                </p>

                <p x-show="error" x-text="error" class="text-sm text-error-500"></p>

                <div class="flex justify-end gap-2 pt-1">
                    <x-ui.button type="button" variant="outline" x-on:click="close()" x-bind:disabled="paying">Batal</x-ui.button>
                    <x-ui.button type="button" class="min-w-36" x-bind:disabled="!canPay" x-on:click="pay()">
                        <x-svg.spinner class="size-4" x-show="paying" x-cloak />
                        <span x-text="paying ? 'Memproses…' : 'Lunasi Tagihan'">Lunasi Tagihan</span>
                    </x-ui.button>
                </div>
            </div>
        </template>
    </x-common.modal>
</div>

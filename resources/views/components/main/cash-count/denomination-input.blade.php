@use('App\Support\CashDenominations')

{{--
    Input uang fisik di laci: per pecahan (jumlah lembar/keping, total dihitung otomatis)
    atau total langsung. Dikirim sebagai count_mode, denominations[nilai], actual_cash.
    Validasi & perhitungan di server: App\Support\CashDenominations::validate().
    Props: idPrefix — awalan id input agar unik bila ada lebih dari satu form di halaman.
--}}
@props(['idPrefix' => 'cash'])

@php
    $oldQty = (array) old('denominations', []);
    $qty = collect(CashDenominations::all())->mapWithKeys(fn ($v) => [$v => (int) ($oldQty[$v] ?? 0) ?: ''])->all();
    $groups = ['Uang Kertas' => CashDenominations::NOTES, 'Uang Logam' => CashDenominations::COINS];
@endphp

<div {{ $attributes->class('space-y-4') }}
    x-data="{
        mode: @js(old('count_mode', 'pecahan')),
        qty: @js($qty),
        get total() {
            return Object.entries(this.qty).reduce((sum, [value, n]) => sum + Number(value) * (parseInt(n) || 0), 0);
        },
        rupiah(n) { return 'Rp' + Number(n).toLocaleString('id-ID'); },
    }">
    <input type="hidden" name="count_mode" x-bind:value="mode">

    <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900" role="tablist" aria-label="Cara menghitung uang">
        <button type="button" role="tab" x-on:click="mode = 'pecahan'" x-bind:aria-selected="mode === 'pecahan'"
            x-bind:class="mode === 'pecahan' ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white' : 'text-gray-500 dark:text-gray-400'"
            class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors">Per Pecahan</button>
        <button type="button" role="tab" x-on:click="mode = 'total'" x-bind:aria-selected="mode === 'total'"
            x-bind:class="mode === 'total' ? 'bg-white text-gray-900 shadow-theme-xs dark:bg-gray-800 dark:text-white' : 'text-gray-500 dark:text-gray-400'"
            class="rounded-md px-3 py-1.5 text-sm font-medium transition-colors">Total Langsung</button>
    </div>

    {{-- Per pecahan --}}
    <div x-show="mode === 'pecahan'" class="space-y-4">
        @foreach ($groups as $groupLabel => $values)
            <div>
                <p class="mb-2 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">{{ $groupLabel }}</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    @foreach ($values as $value)
                        <label for="{{ $idPrefix }}_d{{ $value }}"
                            class="flex items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 dark:border-gray-800">
                            <span class="w-20 shrink-0 text-sm font-medium text-gray-800 dark:text-white/90">{{ CashDenominations::label($value) }}</span>
                            <span class="text-sm text-gray-400">×</span>
                            <input id="{{ $idPrefix }}_d{{ $value }}" type="number" min="0" step="1" inputmode="numeric" placeholder="0"
                                name="denominations[{{ $value }}]" x-model="qty[{{ $value }}]" x-bind:disabled="mode !== 'pecahan'"
                                class="h-9 w-20 rounded-md border border-gray-300 bg-transparent px-2 text-end text-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:border-gray-700 dark:text-white/90" />
                            <span class="ms-auto text-end text-xs text-gray-500 tabular-nums dark:text-gray-400"
                                x-text="rupiah({{ $value }} * (parseInt(qty[{{ $value }}]) || 0))"></span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3 dark:bg-white/3">
            <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Total Uang Fisik</span>
            <span class="text-lg font-bold text-gray-800 tabular-nums dark:text-white/90" x-text="rupiah(total)"></span>
        </div>
        <p data-error-for="denominations" @class(['text-sm text-error-500 dark:text-error-400', 'hidden' => ! $errors->has('denominations')])>{{ $errors->first('denominations') }}</p>
    </div>

    {{-- Total langsung --}}
    <div x-show="mode === 'total'" x-cloak>
        <x-common.form.field label="Total Uang Fisik di Laci" for="{{ $idPrefix }}_actual_cash" error="actual_cash" required>
            {{-- Saat mode "pecahan", server mengabaikan actual_cash dan memakai total pecahan. --}}
            <x-common.form.money id="{{ $idPrefix }}_actual_cash" name="actual_cash" :value="old('actual_cash')"
                :invalid="$errors->has('actual_cash')" />
        </x-common.form.field>
    </div>
</div>

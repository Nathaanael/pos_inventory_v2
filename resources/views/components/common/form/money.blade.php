{{--
    Input rupiah ("Rp 15.000" di layar, "15000" yang dikirim). Lihat resources/js/components/crud/money-input.js.
    Props:
      name     nama field yang dikirim (boleh kosong bila memakai :name lewat atribut "x-bind-name")
      value    nilai awal (form biasa)
      model    ekspresi Alpine untuk diikat dua arah (mis. "form.harga" atau "row.harga_beli")
      bindName ekspresi Alpine untuk nama field dinamis (mis. "`suppliers[${index}][harga_beli]`")
      prefix   default "Rp"
--}}
@props([
    'name' => null,
    'value' => null,
    'model' => null,
    'bindName' => null,
    'prefix' => 'Rp',
    'invalid' => false,
    'id' => null,
])
<div x-data="moneyInput(@js($value))" @if ($model) x-modelable="raw" x-model="{{ $model }}" @endif
    class="flex h-11 w-full overflow-hidden rounded-[10px] border bg-white shadow-theme-xs transition-colors focus-within:border-brand-300 focus-within:ring-3 focus-within:ring-brand-500/20 dark:bg-gray-900 dark:focus-within:border-brand-500/40 {{ $invalid ? 'border-error-500 dark:border-error-500/50' : 'border-gray-300 dark:border-gray-700' }}">
    <span class="flex items-center border-e border-gray-200 bg-gray-50 px-3.5 text-sm text-gray-500 dark:border-gray-800 dark:bg-white/3 dark:text-gray-400">{{ $prefix }}</span>
    <input type="text" inputmode="numeric" autocomplete="off" @if ($id) id="{{ $id }}" @endif
        x-bind:value="display" x-on:input="onInput($event)"
        {{ $attributes->except(['class'])->merge(['class' => 'h-full min-w-0 flex-1 bg-transparent px-4 text-sm text-gray-800 outline-hidden placeholder:text-gray-400 dark:text-white/90 dark:placeholder:text-gray-500']) }} />
    <input type="hidden" @if ($name) name="{{ $name }}" @endif @if ($bindName) x-bind:name="{{ $bindName }}" @endif x-bind:value="raw">
</div>

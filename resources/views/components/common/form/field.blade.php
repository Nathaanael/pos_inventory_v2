{{--
    Pembungkus field form: label, input (slot), teks bantuan, dan pesan validasi.
    Props:
      label, for, required, hint
      error   kunci error validasi (mis. "name" atau "suppliers.0.harga_beli"); default = for
--}}
@props([
    'label' => null,
    'for' => null,
    'required' => false,
    'hint' => null,
    'error' => null,
])
@php $errorKey = $error ?? $for; @endphp
<div {{ $attributes->merge(['class' => 'flex flex-col gap-1.5']) }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="text-sm font-medium text-gray-700 dark:text-gray-400">
            {{ $label }}@if ($required)<span class="text-error-500">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $hint }}</p>
    @endif

    @if ($errorKey)
        {{-- data-error-for: diisi ajaxForm saat validasi AJAX gagal. --}}
        @error($errorKey)
            <p class="text-sm text-error-500 dark:text-error-400" data-error-for="{{ $errorKey }}">{{ $message }}</p>
        @else
            <p class="hidden text-sm text-error-500 dark:text-error-400" data-error-for="{{ $errorKey }}"></p>
        @enderror
    @endif
</div>

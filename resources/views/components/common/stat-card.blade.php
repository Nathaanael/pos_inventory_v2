{{--
    Kartu statistik (gaya metrik TailAdmin; pengganti Stat widget Filament).
    Props: label, value, description, color (brand|success|error|warning|info), icon (path SVG outline 24x24).
--}}
@props([
    'label',
    'value',
    'description' => null,
    'color' => 'brand',
    'icon' => null,
])
@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400',
        'success' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
        'error' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
        'warning' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400',
        'info' => 'bg-blue-light-50 text-blue-light-500 dark:bg-blue-light-500/15 dark:text-blue-light-500',
    ];
    $descTones = [
        'brand' => 'text-brand-500 dark:text-brand-400',
        'success' => 'text-success-600 dark:text-success-500',
        'error' => 'text-error-600 dark:text-error-500',
        'warning' => 'text-warning-600 dark:text-orange-400',
        'info' => 'text-blue-light-500',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-gray-200 bg-white p-5 md:p-6 dark:border-gray-800 dark:bg-white/3']) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</span>
            <h4 class="mt-2 truncate text-title-sm font-bold text-gray-800 dark:text-white/90">{{ $value }}</h4>
        </div>
        @if ($icon)
            <span class="flex size-12 shrink-0 items-center justify-center rounded-xl {{ $tones[$color] ?? $tones['brand'] }}">
                <svg class="size-6 stroke-current" viewBox="0 0 24 24" fill="none" stroke-width="1.5" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
            </span>
        @endif
    </div>
    @if ($description)
        <p class="mt-3 text-sm {{ $descTones[$color] ?? $descTones['brand'] }}">{{ $description }}</p>
    @endif
</div>

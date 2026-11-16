{{--
    Panel geser dari sisi kanan (pengganti ->slideOver() Filament).
    Props: show (ekspresi Alpine), close, title, maxWidth (lg | xl | 2xl | 4xl | 6xl).
    Slot: heading (opsional), default, footer.
--}}
@props([
    'show',
    'close' => null,
    'title' => null,
    'maxWidth' => '4xl',
])
@php
    $close ??= "{$show} = false";
    $width = [
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '4xl' => 'max-w-4xl',
        '6xl' => 'max-w-6xl',
    ][$maxWidth] ?? 'max-w-4xl';
@endphp
<template x-teleport="body">
    <div x-show="{{ $show }}" x-cloak role="dialog" aria-modal="true" class="fixed inset-0 z-99999"
        x-on:keydown.escape.window="if ({{ $show }}) { {{ $close }} }"
        x-init="$watch('{{ $show }}', (open) => document.body.classList.toggle('overflow-hidden', open))">
        <div class="absolute inset-0 bg-gray-400/50 backdrop-blur-sm dark:bg-black/70" x-on:click="{{ $close }}"
            x-show="{{ $show }}" x-transition.opacity></div>

        <div class="absolute inset-y-0 end-0 flex w-full {{ $width }}"
            x-show="{{ $show }}"
            x-transition:enter="transform transition ease-out duration-200"
            x-transition:enter-start="ltr:translate-x-full rtl:-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="ltr:translate-x-full rtl:-translate-x-full">
            <div class="flex h-full w-full flex-col bg-white shadow-theme-xl dark:bg-gray-900">
                <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-6 py-5 dark:border-gray-800">
                    <h3 class="truncate text-lg font-semibold text-gray-800 dark:text-white/90">{{ $heading ?? $title }}</h3>
                    <button type="button" x-on:click="{{ $close }}" aria-label="Tutup"
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                        <x-svg.x class="size-5" />
                    </button>
                </div>

                <div class="custom-scrollbar flex-1 overflow-y-auto px-6 py-6">{{ $slot }}</div>

                @isset($footer)
                    <div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </div>
</template>

{{--
    Modal TailAdmin yang dikendalikan variabel Alpine milik halaman.

    Props:
      show        ekspresi Alpine untuk status buka (mis. "modalOpen")
      close       ekspresi Alpine untuk menutup (default: "{show} = false")
      title       judul (boleh ekspresi Alpine lewat slot "heading")
      description
      maxWidth    sm | md | lg | xl | 2xl | 3xl | 4xl
    Slot: heading (opsional), default (isi), footer (tombol)
--}}
@props([
    'show',
    'close' => null,
    'title' => null,
    'description' => null,
    'maxWidth' => 'lg',
])
@php
    $close ??= "{$show} = false";
    $width = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl',
        '4xl' => 'sm:max-w-4xl',
    ][$maxWidth] ?? 'sm:max-w-lg';
@endphp
<template x-teleport="body">
    <div x-show="{{ $show }}" x-cloak role="dialog" aria-modal="true"
        class="fixed inset-0 z-99999 flex items-start justify-center overflow-y-auto p-4 sm:items-center sm:p-6"
        x-on:keydown.escape.window="if ({{ $show }}) { {{ $close }} }"
        x-init="$watch('{{ $show }}', (open) => {
            document.body.classList.toggle('overflow-hidden', open);
            if (open) $nextTick(() => $el.querySelector('[autofocus], input:not([type=hidden]):not([readonly]), select, textarea')?.focus());
        })">
        <div class="fixed inset-0 bg-gray-400/50 backdrop-blur-[32px] dark:bg-black/70" x-on:click="{{ $close }}"
            x-show="{{ $show }}" x-transition.opacity></div>

        <div {{ $attributes->merge(['class' => "relative my-auto w-full {$width} rounded-3xl border border-gray-200 bg-white p-6 shadow-theme-xl sm:p-8 dark:border-gray-800 dark:bg-gray-900"]) }}
            x-show="{{ $show }}" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100">
            <button type="button" x-on:click="{{ $close }}" aria-label="Tutup"
                class="absolute end-4 top-4 flex size-10 items-center justify-center rounded-full bg-gray-100 text-gray-400 transition-colors hover:bg-gray-200 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
                <x-svg.x class="size-5" />
            </button>

            @if (isset($heading) || $title)
                <div class="mb-6 pe-12">
                    <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90">{{ $heading ?? $title }}</h3>
                    @if ($description)
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
                    @endif
                </div>
            @endif

            {{ $slot }}

            @isset($footer)
                <div class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">{{ $footer }}</div>
            @endisset
        </div>
    </div>
</template>

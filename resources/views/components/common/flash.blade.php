{{--
    Notifikasi (toast) dari session flash — pengganti Filament Notification.
      session('success') = 'Judul'
      session('error')   = 'Judul'  atau  ['Judul', 'Keterangan']
--}}
@php
    $toasts = [];
    foreach (['success', 'error', 'warning'] as $type) {
        if ($message = session($type)) {
            [$title, $body] = is_array($message) ? [$message[0] ?? '', $message[1] ?? null] : [$message, null];
            $toasts[] = compact('type', 'title', 'body');
        }
    }
    $styles = [
        'success' => ['icon' => 'check-circle', 'class' => 'text-success-500 bg-success-50 dark:bg-success-500/15'],
        'error' => ['icon' => 'octagon-alert', 'class' => 'text-error-500 bg-error-50 dark:bg-error-500/15'],
        'warning' => ['icon' => 'info-circle', 'class' => 'text-warning-500 bg-warning-50 dark:bg-warning-500/15'],
    ];
@endphp
<div class="pointer-events-none fixed end-4 top-4 z-999999 flex w-full max-w-sm flex-col gap-3 sm:end-6 sm:top-6" aria-live="polite">
    @foreach ($toasts as $toast)
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-cloak
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $styles[$toast['type']]['class'] }}">
                <x-dynamic-component :component="'svg.' . $styles[$toast['type']]['icon']" class="size-5" />
            </span>
            <div class="min-w-0 flex-1 pt-1">
                <p class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $toast['title'] }}</p>
                @if ($toast['body'])
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $toast['body'] }}</p>
                @endif
            </div>
            <button type="button" x-on:click="show = false" aria-label="Tutup" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <x-svg.x class="size-4" />
            </button>
        </div>
    @endforeach
</div>

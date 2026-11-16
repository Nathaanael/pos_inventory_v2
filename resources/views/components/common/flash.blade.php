{{--
    Notifikasi (toast) — pengganti Filament Notification.
    Dari server (session flash):
      session('success'|'error'|'warning') = 'Judul'  atau  ['Judul', 'Keterangan']
    Dari JavaScript:
      window.dispatchEvent(new CustomEvent('toast', { detail: { type: 'error', title: 'Stok habis', body: '...' } }))
--}}
@php
    $toasts = [];
    foreach (['success', 'error', 'warning'] as $type) {
        if ($message = session($type)) {
            [$title, $body] = is_array($message) ? [$message[0] ?? '', $message[1] ?? null] : [$message, null];
            $toasts[] = compact('type', 'title', 'body');
        }
    }
@endphp
<div x-data="{
        toasts: [],
        nextId: 0,
        push(toast) {
            const id = ++this.nextId;
            this.toasts.push({ id, show: true, type: 'success', body: null, ...toast });
            setTimeout(() => this.dismiss(id), 5000);
        },
        dismiss(id) {
            const toast = this.toasts.find((t) => t.id === id);
            if (toast) toast.show = false;
            setTimeout(() => this.toasts = this.toasts.filter((t) => t.id !== id), 200);
        },
        init() {
            @js($toasts).forEach((toast) => this.push(toast));
        },
    }"
    x-on:toast.window="push($event.detail)"
    class="pointer-events-none fixed end-4 top-4 z-999999 flex w-full max-w-sm flex-col gap-3 sm:end-6 sm:top-6" aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-show="toast.show" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full"
                x-bind:class="{
                    'text-success-500 bg-success-50 dark:bg-success-500/15': toast.type === 'success',
                    'text-error-500 bg-error-50 dark:bg-error-500/15': toast.type === 'error',
                    'text-warning-500 bg-warning-50 dark:bg-warning-500/15': toast.type === 'warning',
                }">
                <x-svg.check-circle class="size-5" x-show="toast.type === 'success'" />
                <x-svg.octagon-alert class="size-5" x-show="toast.type === 'error'" />
                <x-svg.info-circle class="size-5" x-show="toast.type === 'warning'" />
            </span>
            <div class="min-w-0 flex-1 pt-1">
                <p class="text-sm font-semibold text-gray-800 dark:text-white/90" x-text="toast.title"></p>
                <p x-show="toast.body" class="mt-1 text-sm text-gray-500 dark:text-gray-400" x-text="toast.body"></p>
            </div>
            <button type="button" x-on:click="dismiss(toast.id)" aria-label="Tutup" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <x-svg.x class="size-4" />
            </button>
        </div>
    </template>
</div>

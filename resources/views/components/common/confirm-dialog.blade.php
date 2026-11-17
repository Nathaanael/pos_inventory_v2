{{--
    Dialog konfirmasi global (dipasang sekali di layout). Dibuka lewat event:

      $dispatch('confirm', {
          title: 'Hapus Kategori',
          message: 'Apakah Anda yakin ingin menghapus kategori ini?',
          action: '/categories/5',         // URL form
          method: 'DELETE',                // DELETE | POST | PUT | PATCH
          confirmLabel: 'Hapus',
          variant: 'destructive',          // 'destructive' | 'success' | 'default'
          fields: [['ids[]', 1], ['ids[]', 2]],  // input tersembunyi tambahan (opsional)
          reason: { name: 'void_reason', label: 'Alasan Pembatalan' },  // isian teks wajib (opsional)
      })
--}}
<div x-data="{
        open: false,
        title: '',
        message: '',
        action: '',
        method: 'DELETE',
        confirmLabel: 'Hapus',
        variant: 'destructive',
        fields: [],
        reason: null,
        reasonText: '',
        submitting: false,
        show(detail) {
            Object.assign(this, {
                title: 'Konfirmasi',
                message: 'Apakah Anda yakin?',
                method: 'DELETE',
                confirmLabel: 'Hapus',
                variant: 'destructive',
                fields: [],
                reason: null,
                reasonText: '',
                submitting: false,
            }, detail);
            this.open = true;
        },
        get tone() {
            return {
                destructive: { icon: 'bg-error-50 text-error-500 dark:bg-error-500/15', button: 'bg-error-500 hover:bg-error-600' },
                success: { icon: 'bg-success-50 text-success-500 dark:bg-success-500/15', button: 'bg-success-500 hover:bg-success-600' },
                default: { icon: 'bg-brand-50 text-brand-500 dark:bg-brand-500/15', button: 'bg-brand-500 hover:bg-brand-600' },
            }[this.variant] ?? { icon: 'bg-brand-50 text-brand-500 dark:bg-brand-500/15', button: 'bg-brand-500 hover:bg-brand-600' };
        },
    }"
    x-on:confirm.window="show($event.detail)"
    {{-- Kembali lewat tombol back (bfcache): jangan biarkan dialog tetap dalam status loading. --}}
    x-on:pageshow.window="if ($event.persisted) { submitting = false; open = false }">
    {{-- Selama memproses, dialog tidak bisa ditutup (Esc / klik luar / tombol X). --}}
    <x-common.modal show="open" close="if (! submitting) open = false" max-width="md">
        <div class="flex flex-col items-center text-center">
            <span class="mb-5 flex size-14 items-center justify-center rounded-full" x-bind:class="tone.icon">
                <x-svg.octagon-alert class="size-7" x-show="variant === 'destructive'" />
                <x-svg.check-circle class="size-7" x-show="variant === 'success'" />
                <x-svg.info-circle class="size-7" x-show="variant === 'default'" />
            </span>
            <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90" x-text="title"></h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" x-text="message"></p>
        </div>

        {{-- Saat dikirim: tombol menampilkan spinner + loading bar sampai halaman berikutnya terbuka. --}}
        <form method="POST" x-bind:action="action" class="mt-6"
            x-on:submit="if (submitting) { $event.preventDefault(); return } submitting = true; $store.loadingBar.start()">
            @csrf
            <input type="hidden" name="_method" x-bind:value="method">
            <template x-for="([name, value], index) in fields" :key="index">
                <input type="hidden" x-bind:name="name" x-bind:value="value">
            </template>

            <template x-if="reason">
                <div class="mb-6 flex flex-col gap-1.5 text-start">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-400">
                        <span x-text="reason.label"></span><span class="text-error-500">*</span>
                    </label>
                    <x-ui.textarea x-bind:name="reason.name" x-model="reasonText" rows="3" required />
                </div>
            </template>

            <div class="mt-2 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
                <x-ui.button variant="outline" x-on:click="open = false" x-bind:disabled="submitting" class="sm:min-w-32">Batal</x-ui.button>
                <button type="submit" x-bind:disabled="submitting || (reason && ! reasonText.trim())" x-bind:aria-busy="submitting"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-[10px] px-4 text-sm font-medium text-white shadow-theme-xs transition-colors disabled:opacity-50 sm:min-w-32"
                    x-bind:class="[tone.button, submitting && 'disabled:opacity-80 cursor-wait']">
                    <svg x-show="submitting" x-cloak class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.3" stroke-width="3" />
                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                    </svg>
                    <span x-text="submitting ? 'Memproses…' : confirmLabel"></span>
                </button>
            </div>
        </form>
    </x-common.modal>
</div>

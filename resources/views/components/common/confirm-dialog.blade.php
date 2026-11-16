{{--
    Dialog konfirmasi global (dipasang sekali di layout). Dibuka lewat event:

      $dispatch('confirm', {
          title: 'Hapus Kategori',
          message: 'Apakah Anda yakin ingin menghapus kategori ini?',
          action: '/categories/5',         // URL form
          method: 'DELETE',                // DELETE | POST | PUT | PATCH
          confirmLabel: 'Hapus',
          variant: 'destructive',          // atau 'default'
          fields: [['ids[]', 1], ['ids[]', 2]],  // input tersembunyi tambahan (opsional)
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
        show(detail) {
            Object.assign(this, {
                title: 'Konfirmasi',
                message: 'Apakah Anda yakin?',
                method: 'DELETE',
                confirmLabel: 'Hapus',
                variant: 'destructive',
                fields: [],
            }, detail);
            this.open = true;
        },
    }"
    x-on:confirm.window="show($event.detail)">
    <x-common.modal show="open" max-width="md">
        <div class="flex flex-col items-center text-center">
            <span class="mb-5 flex size-14 items-center justify-center rounded-full"
                x-bind:class="variant === 'destructive' ? 'bg-error-50 text-error-500 dark:bg-error-500/15' : 'bg-brand-50 text-brand-500 dark:bg-brand-500/15'">
                <x-svg.octagon-alert class="size-7" x-show="variant === 'destructive'" />
                <x-svg.info-circle class="size-7" x-show="variant !== 'destructive'" />
            </span>
            <h3 class="text-xl font-semibold text-gray-800 dark:text-white/90" x-text="title"></h3>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400" x-text="message"></p>
        </div>

        <form method="POST" x-bind:action="action" class="mt-8 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
            @csrf
            <input type="hidden" name="_method" x-bind:value="method">
            <template x-for="([name, value], index) in fields" :key="index">
                <input type="hidden" x-bind:name="name" x-bind:value="value">
            </template>

            <x-ui.button variant="outline" x-on:click="open = false" class="sm:min-w-32">Batal</x-ui.button>
            <button type="submit" x-text="confirmLabel"
                class="inline-flex h-10 items-center justify-center rounded-[10px] px-4 text-sm font-medium text-white shadow-theme-xs transition-colors sm:min-w-32"
                x-bind:class="variant === 'destructive' ? 'bg-error-500 hover:bg-error-600' : 'bg-brand-500 hover:bg-brand-600'"></button>
        </form>
    </x-common.modal>
</div>

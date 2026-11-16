{{-- Bar aksi massal (muncul saat ada baris dicentang). Variabel: $title, $message, $action. --}}
<div x-show="selected.length" x-cloak
    class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-brand-50/60 px-5 py-3 sm:px-6 dark:border-gray-800 dark:bg-brand-500/5">
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
        <span x-text="selected.length"></span> data dipilih
    </span>
    <div class="flex items-center gap-2">
        <x-ui.button size="sm" variant="ghost" x-on:click="selected = []">Batal pilih</x-ui.button>
        <x-ui.button size="sm" variant="destructive"
            x-on:click="confirm({ title: {{ \Illuminate\Support\Js::from($title) }}, message: {{ \Illuminate\Support\Js::from($message) }}, action: {{ \Illuminate\Support\Js::from($action) }}, fields: selected.map(id => ['ids[]', id]) })">
            <x-svg.trash-bin class="size-4" />
            Hapus
        </x-ui.button>
    </div>
</div>

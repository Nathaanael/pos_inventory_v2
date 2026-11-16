{{--
    Bar aksi massal untuk listPage(). Variabel: $actions = [
        ['label' => 'Hapus', 'variant' => 'destructive', 'confirm' => [title, message, action, method?, confirmLabel?, variant?]],
    ]
--}}
@use('Illuminate\Support\Js')
<div x-show="selected.length" x-cloak
    class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 bg-brand-50/60 px-5 py-3 sm:px-6 dark:border-gray-800 dark:bg-brand-500/5">
    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
        <span x-text="selected.length"></span> data dipilih
    </span>
    <div class="flex flex-wrap items-center gap-2">
        <x-ui.button size="sm" variant="ghost" x-on:click="selected = []">Batal pilih</x-ui.button>
        @foreach ($actions as $action)
            <x-ui.button size="sm" :variant="$action['variant'] ?? 'outline'" x-on:click="confirmBulk({{ Js::from($action['confirm']) }})">
                {{ $action['label'] }}
            </x-ui.button>
        @endforeach
    </div>
</div>

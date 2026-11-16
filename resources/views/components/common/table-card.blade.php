{{--
    Kartu tabel data dengan desain "Basic Table 3" TailAdmin: judul, pencarian, filter, tombol aksi, tabel, paginasi.
    Pencarian & filter memakai GET (?q=...&filter=...), jadi bisa di-bookmark dan tombol back tetap bekerja.

    Props:
      title, description
      paginator          LengthAwarePaginator untuk footer paginasi (opsional)
      search             tampilkan kotak pencarian (default true)
      searchPlaceholder
    Slot:
      filters            <select name="..."> tambahan (otomatis submit saat berubah)
      actions            tombol di kanan atas (mis. "Tambah ...")
      toolbar            baris di atas tabel (mis. aksi massal)
      default            isi <table>: <thead> dan <tbody>
--}}
@props([
    'title' => null,
    'description' => null,
    'paginator' => null,
    'search' => true,
    'searchPlaceholder' => 'Cari...',
])
<x-ui.card {{ $attributes->merge(['class' => 'w-full max-w-full min-w-0 gap-0 overflow-hidden rounded-2xl border border-gray-200 bg-white p-0 sm:p-0 dark:border-gray-800 dark:bg-white/3']) }}>
    <!-- Table Header Controls -->
    <div class="flex flex-col gap-4 px-5 py-4 lg:flex-row lg:items-center lg:justify-between sm:px-6">
        <div class="min-w-0">
            @if ($title)
                <x-ui.card.title class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</x-ui.card.title>
            @endif
            @if ($description)
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>
            @endif
        </div>

        <div class="flex w-full flex-col gap-3 sm:flex-row sm:items-center lg:w-auto">
            @if ($search || isset($filters))
                <form method="GET" action="{{ url()->current() }}" class="flex w-full flex-col gap-3 sm:flex-row sm:items-center lg:w-auto">
                    {{ $filters ?? '' }}

                    @if ($search)
                        <x-ui.input-group class="h-10 w-full sm:w-64 xl:w-75">
                            <x-ui.input-group.addon align="inline-start">
                                <x-svg.search class="size-5 text-gray-400" />
                            </x-ui.input-group.addon>
                            <x-ui.input-group.input
                                type="search"
                                name="q"
                                value="{{ request('q') }}"
                                placeholder="{{ $searchPlaceholder }}"
                                autocomplete="off"
                                x-data
                                x-init="if ($el.value) { $el.focus(); $el.setSelectionRange($el.value.length, $el.value.length); }"
                                x-on:input.debounce.500ms="$el.form.requestSubmit()" />
                        </x-ui.input-group>
                    @endif
                </form>
            @endif

            @isset($actions)
                <div class="flex shrink-0 items-center gap-3">{{ $actions }}</div>
            @endisset
        </div>
    </div>

    {{ $toolbar ?? '' }}

    <!-- Table Content -->
    <x-ui.card.content class="p-0">
        <x-ui.scroll-area class="w-full" viewport-class="overflow-x-auto custom-scrollbar">
            <table class="min-w-full">
                {{ $slot }}
            </table>
        </x-ui.scroll-area>
    </x-ui.card.content>

    @if ($paginator)
        <x-common.table-pagination :paginator="$paginator" class="border-t border-gray-100 dark:border-gray-800" />
    @endif
</x-ui.card>

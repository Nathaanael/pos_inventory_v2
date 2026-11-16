{{--
    Paginasi server-side (Laravel paginator) dengan tampilan footer "Basic Table 3" TailAdmin.
    Props: paginator (LengthAwarePaginator).
--}}
@use('App\Support\Ui\ButtonVariants')
@props(['paginator'])
@php
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $disabled = 'pointer-events-none cursor-not-allowed opacity-50';
    $link = ButtonVariants::classes('outline', 'icon');
    $linkActive = ButtonVariants::classes('default', 'icon');

    // Jendela halaman sama seperti contoh TailAdmin: maksimal 7 tombol dengan elipsis.
    $pages = match (true) {
        $last <= 7 => range(1, max($last, 1)),
        $current <= 4 => [1, 2, 3, 4, 5, '…', $last],
        $current >= $last - 3 => [1, '…', $last - 4, $last - 3, $last - 2, $last - 1, $last],
        default => [1, '…', $current - 1, $current, $current + 1, '…', $last],
    };
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6']) }}>
    <p class="text-sm text-gray-500 dark:text-gray-400">
        @if ($paginator->total() > 0)
            Menampilkan <span class="font-medium text-gray-700 dark:text-gray-300">{{ $paginator->firstItem() }}</span>
            sampai <span class="font-medium text-gray-700 dark:text-gray-300">{{ $paginator->lastItem() }}</span>
            dari <span class="font-medium text-gray-700 dark:text-gray-300">{{ $paginator->total() }}</span> data
        @else
            Tidak ada data
        @endif
    </p>

    @if ($paginator->hasPages())
        <nav aria-label="Paginasi" class="flex items-center gap-1.5">
            <a href="{{ $paginator->previousPageUrl() ?? '#' }}" aria-label="Halaman sebelumnya"
                class="{{ $link }} {{ $paginator->onFirstPage() ? $disabled : '' }}" @if ($paginator->onFirstPage()) aria-disabled="true" @endif>
                <x-svg.chevron-left class="rtl:rotate-180" />
            </a>

            <span class="px-2 text-sm font-medium text-gray-700 sm:hidden dark:text-gray-400">{{ $current }}/{{ $last }}</span>

            @foreach ($pages as $page)
                @if (is_string($page))
                    <span class="hidden size-10 items-center justify-center text-sm text-gray-500 sm:flex dark:text-gray-400">…</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="{{ $page === $current ? $linkActive : $link }} hidden sm:inline-flex"
                        @if ($page === $current) aria-current="page" @endif>{{ $page }}</a>
                @endif
            @endforeach

            <a href="{{ $paginator->nextPageUrl() ?? '#' }}" aria-label="Halaman berikutnya"
                class="{{ $link }} {{ $paginator->hasMorePages() ? '' : $disabled }}" @unless ($paginator->hasMorePages()) aria-disabled="true" @endunless>
                <x-svg.chevron-right class="rtl:rotate-180" />
            </a>
        </nav>
    @endif
</div>

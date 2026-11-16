{{-- Ikon ya/tidak (pengganti IconColumn::boolean() Filament). Variabel: $value. --}}
@if ($value)
    <span class="inline-flex size-7 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500" title="Ya">
        <x-svg.check class="size-4" />
    </span>
@else
    <span class="inline-flex size-7 items-center justify-center rounded-full bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500" title="Tidak">
        <x-svg.x class="size-4" />
    </span>
@endif

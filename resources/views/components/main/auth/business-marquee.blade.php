@use('App\Enums\BusinessType')

{{--
    Dua kolom carousel vertikal berisi ilustrasi jenis usaha: kolom kiri bergerak naik,
    kolom kanan turun. Isi tiap kolom ditulis dua kali agar loop tidak terlihat sambungannya.
--}}
@props([])

@php
    $cards = collect(BusinessType::cases());
    $columns = [
        ['items' => $cards, 'animation' => 'animate-marquee-up'],
        ['items' => $cards->reverse()->values(), 'animation' => 'animate-marquee-down'],
    ];
@endphp

<div {{ $attributes->class('grid h-full grid-cols-2 gap-5 overflow-hidden') }} aria-hidden="true">
    @foreach ($columns as $column)
        <div class="{{ $column['animation'] }} flex flex-col hover:[animation-play-state:paused] motion-reduce:animate-none">
            @foreach ([...$column['items'], ...$column['items']] as $business)
                {{-- pb-5 (bukan gap) supaya kedua salinan sama tinggi persis → geser -50% mulus. --}}
                <div class="pb-5">
                    <figure class="rounded-2xl bg-white/5 p-3 ring-1 ring-white/10 dark:bg-white/[0.03]">
                        <img src="{{ $business->illustration() }}" alt="" loading="lazy"
                            class="aspect-4/3 w-full rounded-xl object-cover" />
                        <figcaption class="px-1 pt-3 pb-1">
                            <p class="text-sm font-semibold text-white">{{ $business->label() }}</p>
                            <p class="mt-0.5 text-xs text-white/60">{{ $business->description() }}</p>
                        </figcaption>
                    </figure>
                </div>
            @endforeach
        </div>
    @endforeach
</div>

{{--
    Judul halaman. (Breadcrumb "Dashboard > ..." sengaja tidak ditampilkan.)
    Props: pageTitle. Slot opsional: tombol di sisi kanan judul.
--}}
@props(['pageTitle' => 'Page', 'parents' => []])
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-xl font-semibold text-gray-800 dark:text-white/90">
        {{ __($pageTitle) }}
    </h2>
    {{ $slot }}
</div>

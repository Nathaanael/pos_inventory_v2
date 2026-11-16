@php
    $user = auth()->user();
    $initials = collect(explode(' ', (string) $user?->name))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp
<x-ui.dropdown-menu>
    <x-ui.dropdown-menu.trigger class="dropdown-toggle flex items-center text-gray-700 dark:text-gray-400">
        <span class="me-3 flex h-11 w-11 items-center justify-center overflow-hidden rounded-full bg-brand-50 text-sm font-semibold text-brand-500 dark:bg-brand-500/15 dark:text-brand-400">
            {{ $initials ?: '?' }}
        </span>

        <span class="me-1 block text-sm font-medium">{{ $user?->name }}</span>

        <x-svg.chevron-down class="size-5 text-gray-500 duration-200 dark:text-gray-400" x-bind:class="open ? 'rotate-180' : 'rotate-0'" />
    </x-ui.dropdown-menu.trigger>

    <x-ui.dropdown-menu.content align="end" :side-offset="12" class="w-65 p-3">
        <div class="px-3 py-2">
            <span class="block text-sm font-medium text-gray-700 dark:text-gray-400">{{ $user?->name }}</span>
            <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $user?->email }}</span>
            <x-ui.badge size="sm" :color="$user?->isKasir() ? 'info' : 'warning'" class="mt-2">
                {{ \App\Models\User::ROLES[$user?->role] ?? $user?->role }}
            </x-ui.badge>
        </div>


        <x-ui.dropdown-menu.separator />

        <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
        <x-ui.dropdown-menu.item variant="destructive" x-on:click.capture="document.getElementById('logout-form').submit()">
            <x-svg.log-out />
            Keluar
        </x-ui.dropdown-menu.item>
    </x-ui.dropdown-menu.content>
</x-ui.dropdown-menu>

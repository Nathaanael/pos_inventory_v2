{{--
    Notifikasi lonceng (pengganti databaseNotifications() Filament): 15 notifikasi terakhir milik user.
    Mendukung format notifikasi baru (App\Notifications\AppNotification) maupun format Filament lama yang masih tersimpan.
--}}
@php
    $user = auth()->user();
    $notifications = $user?->notifications()->latest()->limit(15)->get() ?? collect();
    $unread = $user?->unreadNotifications()->count() ?? 0;

    $normalize = function ($notification) {
        $data = $notification->data;
        $url = $data['url'] ?? ($data['actions'][0]['url'] ?? null);
        $type = $data['type'] ?? match ($data['iconColor'] ?? $data['status'] ?? null) {
            'success' => 'success',
            'danger' => 'error',
            'warning' => 'warning',
            default => 'info',
        };

        return [
            'title' => $data['title'] ?? 'Notifikasi',
            'body' => $data['body'] ?? null,
            'url' => $url,
            'url_label' => $data['url_label'] ?? ($data['actions'][0]['label'] ?? 'Buka'),
            'type' => $type,
            'unread' => $notification->read_at === null,
            'time' => $notification->created_at?->locale('id')->diffForHumans(),
        ];
    };
    $tones = [
        'success' => 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-500',
        'error' => 'bg-error-50 text-error-600 dark:bg-error-500/15 dark:text-error-500',
        'warning' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-orange-400',
        'info' => 'bg-brand-50 text-brand-500 dark:bg-brand-500/15 dark:text-brand-400',
    ];
@endphp

<x-ui.dropdown-menu>
    <x-ui.dropdown-menu.trigger as-child>
        <x-ui.button variant="outline" size="icon-lg" class="relative" aria-label="Notifikasi">
            @if ($unread > 0)
                <span class="absolute -top-1 end-0 z-10 flex min-w-5 translate-x-1/3 items-center justify-center rounded-full bg-orange-400 px-1 text-[10px] font-semibold text-white rtl:-translate-x-1/3">
                    {{ $unread > 9 ? '9+' : $unread }}
                </span>
            @endif
            <x-svg.bell class="size-auto!" />
        </x-ui.button>
    </x-ui.dropdown-menu.trigger>

    <x-ui.dropdown-menu.content align="end" :side-offset="12" class="w-87.5 overflow-hidden p-3 sm:w-90.25">
        <div class="flex items-center justify-between pb-3">
            <h5 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Notifikasi</h5>
            @if ($unread > 0)
                <form method="POST" action="{{ route('notifications.read') }}">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-brand-500 hover:underline">Tandai semua dibaca</button>
                </form>
            @endif
        </div>

        <x-ui.dropdown-menu.separator class="my-0" />

        <div class="custom-scrollbar max-h-96 overflow-x-hidden overflow-y-auto">
            @forelse ($notifications->map($normalize) as $item)
                <div class="flex items-start gap-3 rounded-lg p-3 {{ $item['unread'] ? 'bg-gray-50 dark:bg-white/3' : '' }}">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $tones[$item['type']] ?? $tones['info'] }}">
                        <x-svg.bell class="size-4" />
                    </span>
                    <span class="block min-w-0 flex-1 text-start">
                        <span class="block text-sm font-medium text-gray-800 dark:text-white/90">{{ $item['title'] }}</span>
                        @if ($item['body'])
                            <span class="mt-0.5 block text-sm text-gray-500 dark:text-gray-400">{{ $item['body'] }}</span>
                        @endif
                        <span class="mt-1.5 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $item['time'] }}</span>
                            @if ($item['url'])
                                <a href="{{ $item['url'] }}" target="_blank" class="font-medium text-brand-500 hover:underline">{{ $item['url_label'] }}</a>
                            @endif
                        </span>
                    </span>
                </div>
            @empty
                <p class="px-3 py-10 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada notifikasi.</p>
            @endforelse
        </div>

        @if ($notifications->isNotEmpty())
            <x-ui.dropdown-menu.separator class="my-0" />
            <form method="POST" action="{{ route('notifications.clear') }}" class="pt-2">
                @csrf
                @method('DELETE')
                <button type="submit" class="w-full rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5">Hapus semua notifikasi</button>
            </form>
        @endif
    </x-ui.dropdown-menu.content>
</x-ui.dropdown-menu>

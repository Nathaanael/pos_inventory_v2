<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi sederhana di database (pengganti Filament database notifications),
 * tampil di ikon lonceng header. Dipakai mis. saat file export selesai dibuat.
 */
class AppNotification extends Notification
{
    use Queueable;

    /**
     * @param  'success'|'error'|'warning'|'info'  $type
     */
    public function __construct(
        public string $title,
        public ?string $body = null,
        public string $type = 'info',
        public ?string $url = null,
        public ?string $urlLabel = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'url' => $this->url,
            'url_label' => $this->urlLabel,
        ];
    }
}

<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PlatformAdminAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $event,
        public readonly string $title,
        public readonly string $body,
        public readonly string $actionUrl,
        public readonly array $meta = [],
    ) {
        // Persist only after surrounding DB transactions commit (avoids sqlite locks).
        // Keep immediate delivery in unit tests for RefreshDatabase isolation.
        if (! app()->runningUnitTests()) {
            $this->afterCommit();
        }
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'title' => $this->title,
            'body' => $this->body,
            'action_url' => $this->actionUrl,
            'meta' => $this->meta,
        ];
    }
}

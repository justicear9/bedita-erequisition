<?php

namespace App\Notifications;

use App\Models\Requisition;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Notification;

class RequisitionStatusNotification extends Notification
{
    public function __construct(
        public Requisition $requisition,
        public string $kind,
        public string $message,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'message' => $this->message,
            'requisition_id' => $this->requisition->id,
            'requisition_number' => $this->requisition->number,
            'url' => route('requisitions.show', $this->requisition),
            'play_sound' => true,
        ];
    }

    /**
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'BEDITA eREQUISITION',
            'body' => $this->message,
            'url' => route('requisitions.show', $this->requisition),
            'tag' => 'requisition-'.$this->requisition->id,
        ];
    }
}

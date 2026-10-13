<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EipSystemNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public string $type = 'system',
        public ?string $actionUrl = null,
        public ?string $senderName = null,
        public ?array $extra = null
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        // Email:全域開關開啟、同仁未自行關閉、且有 Email 時才寄
        if (config('eip.mail_notifications') && ($notifiable->mail_notifications ?? true) && ! empty($notifiable->email)) {
            $channels[] = Channels\QueuedMailChannel::class;
        }

        // 手機推播:全域已設定 VAPID 金鑰、同仁沒關、且至少有一台裝置訂閱
        if (app(\App\Services\WebPushService::class)->shouldPush($notifiable)) {
            $channels[] = Channels\WebPushChannel::class;
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'action_url' => $this->actionUrl,
            'sender_name' => $this->senderName,
            'extra' => $this->extra,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}

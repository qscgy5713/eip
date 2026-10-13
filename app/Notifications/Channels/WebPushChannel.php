<?php

namespace App\Notifications\Channels;

use App\Notifications\EipSystemNotification;
use App\Services\WebPushService;

/** 把站內通知同步推播到同仁已訂閱的手機/瀏覽器。 */
class WebPushChannel
{
    public function __construct(private readonly WebPushService $push)
    {
    }

    public function send(object $notifiable, EipSystemNotification $notification): void
    {
        $this->push->sendToUser($notifiable, $notification->title, $notification->message, $notification->actionUrl);
    }
}

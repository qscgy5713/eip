<?php

namespace App\Notifications\Channels;

use App\Mail\EipNotificationMail;
use App\Notifications\EipSystemNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * 將站內通知另外以 Email 排入佇列寄出。
 * 站內通知(database 頻道)仍即時寫入;郵件伺服器或佇列異常時只記錄日誌,不影響簽核等主要流程。
 */
class QueuedMailChannel
{
    public function send(object $notifiable, EipSystemNotification $notification): void
    {
        if (! $notifiable->email) {
            return;
        }

        try {
            Mail::to($notifiable->email)->queue(new EipNotificationMail(
                title: $notification->title,
                body: $notification->message,
                actionUrl: $notification->actionUrl,
                senderName: $notification->senderName,
                recipientName: (string) ($notifiable->name ?? ''),
            ));
        } catch (\Throwable $e) {
            Log::warning('通知 Email 排入佇列失敗:'.$e->getMessage(), ['user_id' => $notifiable->id ?? null]);
        }
    }
}

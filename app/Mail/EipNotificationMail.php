<?php

namespace App\Mail;

use App\Services\Branding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** 站內通知同步寄出的 Email(進佇列,寄送失敗不影響主要操作) */
class EipNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $actionUrl = null,
        public ?string $senderName = null,
        public string $recipientName = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.Branding::name().'] '.$this->title);
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.notification', with: ['appName' => Branding::name()]);
    }
}

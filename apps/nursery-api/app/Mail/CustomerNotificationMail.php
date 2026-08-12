<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $customerName,
        public string $emailSubject,
        public string $headline,
        public string $bodyText,
        public ?string $ctaLabel = null,
        public ?string $ctaUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.customer-notification',
            with: [
                'customerName' => $this->customerName,
                'headline' => $this->headline,
                'bodyText' => $this->bodyText,
                'ctaLabel' => $this->ctaLabel,
                'ctaUrl' => $this->ctaUrl,
            ],
        );
    }
}

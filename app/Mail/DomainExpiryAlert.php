<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DomainExpiryAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Collection $domains,
        public readonly int $alertDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Domain Expiry Alert');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.domain-expiry-alert');
    }
}

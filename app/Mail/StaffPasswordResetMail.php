<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $temporaryPassword,
        public string $staffName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Mavies Bugasan staff password was reset',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.staff-password-reset');
    }
}

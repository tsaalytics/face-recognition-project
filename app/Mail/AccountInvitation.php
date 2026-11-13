<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountInvitation extends Mailable
{
    public function __construct(public string $employeeName, public string $activationUrl, public int $expiresInMinutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'FaceTrack AI - Set Your Password');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-invitation');
    }
}

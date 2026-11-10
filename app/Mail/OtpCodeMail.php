<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Contract $contract, public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Codice di conferma — '.$this->contract->title);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.otp');
    }
}

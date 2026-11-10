<?php

namespace App\Mail;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Contract $contract, public string $packageBytes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Contratto completato — '.$this->contract->title);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.completed');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => $this->packageBytes, 'fascicolo-'.$this->contract->public_id.'.zip')->withMime('application/zip')];
    }
}

<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\MailDelivery;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TrackedMailer
{
    public function send(Contract $contract, string $recipient, string $kind, Mailable $mailable, string $channel = 'email', ?string $mailer = null): MailDelivery
    {
        $delivery = MailDelivery::create(['contract_id' => $contract->id, 'recipient' => $recipient, 'kind' => $kind, 'channel' => $channel, 'status' => 'queued']);
        try {
            $pending = $mailer ? Mail::mailer($mailer)->to($recipient) : Mail::to($recipient);
            $sent = $pending->send($mailable);
            $delivery->update(['status' => 'sent', 'sent_at' => now(), 'message_id' => $sent?->getMessageId()]);
        } catch (Throwable $e) {
            $delivery->update(['status' => 'failed', 'metadata' => ['error' => mb_substr($e->getMessage(), 0, 500)]]);
            throw $e;
        }

        return $delivery;
    }
}

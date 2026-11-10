<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Mail\ContractInvitationMail;
use App\Models\Contract;
use App\Models\Invitation;
use App\Services\AuditService;
use App\Services\TrackedMailer;
use Illuminate\Console\Command;

class SendContractReminders extends Command
{
    protected $signature = 'contracts:remind';

    protected $description = 'Invia promemoria per inviti attivi';

    public function handle(TrackedMailer $mailer, AuditService $audit): int
    {
        Contract::whereIn('status', [ContractStatus::Sent, ContractStatus::Viewed])->with(['invitations' => fn ($q) => $q->latest()])->chunkById(100, function ($contracts) use ($mailer, $audit) {
            foreach ($contracts as $c) {
                $old = $c->invitations->first();
                if (! $old || ! $old->isUsable()) {
                    continue;
                }$due = ($old->reminder_count === 0 && $old->last_sent_at?->lte(now()->subHours(48))) || ($old->reminder_count < 2 && $c->expires_at->between(now(), now()->addHours(24)));
                if (! $due) {
                    continue;
                }$old->update(['revoked_at' => now()]);
                $token = bin2hex(random_bytes(32));
                $new = Invitation::create(['contract_id' => $c->id, 'token_hash' => hash('sha256', $token), 'expires_at' => $c->expires_at, 'last_sent_at' => now(), 'reminder_count' => $old->reminder_count + 1]);
                $delivery = $mailer->send($c, $c->signer_email, 'reminder', new ContractInvitationMail($c, route('signer.claim', $token)));
                $audit->record($c, 'invitation.reminded', ['invitation_id' => $new->id, 'delivery_id' => $delivery->id, 'number' => $new->reminder_count]);
            }
        });

        return self::SUCCESS;
    }
}

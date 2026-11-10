<?php

namespace App\Services;

use App\Enums\ContractStatus;
use App\Mail\ContractInvitationMail;
use App\Models\Contract;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvitationService
{
    public function __construct(private AuditService $audit, private TrackedMailer $mailer) {}

    public function send(Contract $contract, Request $request): void
    {
        DB::transaction(function () use ($contract, $request) {
            $contract = Contract::lockForUpdate()->findOrFail($contract->id);
            abort_unless(in_array($contract->status, [ContractStatus::Draft, ContractStatus::Sent, ContractStatus::Viewed]), 409);
            $contract->invitations()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $token = bin2hex(random_bytes(32));
            $invitation = Invitation::create(['contract_id' => $contract->id, 'token_hash' => hash('sha256', $token), 'expires_at' => $contract->expires_at, 'last_sent_at' => now()]);
            $contract->update(['status' => ContractStatus::Sent]);
            $url = route('signer.claim', ['token' => $token]);
            $delivery = $this->mailer->send($contract, $contract->signer_email, 'invitation', new ContractInvitationMail($contract, $url));
            $this->audit->record($contract, 'invitation.sent', ['invitation_id' => $invitation->id, 'delivery_id' => $delivery->id, 'expires_at' => $invitation->expires_at->toIso8601String()], $request, 'admin', $request->user()->id);
        });
    }
}

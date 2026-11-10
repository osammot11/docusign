<?php

namespace App\Services;

use App\Mail\ContractCompletedMail;
use App\Models\Contract;

class PecService
{
    public function __construct(private TrackedMailer $mailer, private DocumentVault $vault) {}

    public function sendCompleted(Contract $contract): void
    {
        if (! config('evidence.agency_pec') || ! $contract->signer_pec || ! config('mail.mailers.pec.host')) {
            return;
        }
        $this->mailer->send($contract, $contract->signer_pec, 'completed', new ContractCompletedMail($contract, $this->vault->get($contract->package_path)), 'pec', 'pec');
    }
}

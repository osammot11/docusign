<?php

namespace App\Http\Controllers;

use App\Enums\ContractStatus;
use App\Mail\ContractCompletedMail;
use App\Models\Contract;
use App\Models\Signature;
use App\Services\AuditService;
use App\Services\DocumentVault;
use App\Services\EvidencePackageService;
use App\Services\InvitationService;
use App\Services\PecService;
use App\Services\TrackedMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractActionController extends Controller
{
    public function send(Contract $contract, Request $request, InvitationService $service)
    {
        $service->send($contract, $request);

        return back()->with('success', 'Invito inviato.');
    }

    public function countersign(Contract $contract, Request $request, AuditService $audit, EvidencePackageService $evidence, DocumentVault $vault, TrackedMailer $mailer, PecService $pec)
    {
        $request->validate(['password' => 'required|current_password', 'confirm' => 'accepted']);
        DB::transaction(function () use ($contract, $request, $audit) {
            $locked = Contract::lockForUpdate()->findOrFail($contract->id);
            abort_unless($locked->status === ContractStatus::ClientSigned, 409);
            Signature::create([
                'contract_id' => $locked->id, 'party' => 'agency', 'user_id' => $request->user()->id,
                'name' => $request->user()->name, 'role' => 'Rappresentante Produce a Value',
                'email' => $request->user()->email, 'consents' => ['document' => true, 'countersignature' => true],
                'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'signed_at' => now(),
            ]);
            $locked->update([
                'status' => ContractStatus::Completed, 'completed_at' => now(),
                'retention_until' => now()->addYears(config('evidence.retention_years')),
            ]);
            $audit->record($locked, 'signature.agency_completed', ['source_hash' => $locked->source_hash], $request, 'admin', $request->user()->id);
        });

        $contract->refresh();
        $evidence->generate($contract);
        $contract->refresh();
        try {
            $mailer->send($contract, $contract->signer_email, 'completed', new ContractCompletedMail($contract, $vault->get($contract->package_path)));
        } catch (\Throwable $exception) {
            report($exception);
        }
        try {
            $pec->sendCompleted($contract);
        } catch (\Throwable $exception) {
            report($exception);
        }
        $contract->refresh();
        $evidence->refreshPackage($contract);

        return back()->with('success', 'Contratto completato e fascicolo generato.');
    }

    public function void(Contract $contract, Request $request, AuditService $audit, EvidencePackageService $evidence)
    {
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        abort_if($contract->status === ContractStatus::Voided, 409);
        $contract->update([
            'status' => ContractStatus::Voided, 'voided_at' => now(), 'void_reason' => $data['reason'],
            'retention_until' => now()->addYears(config('evidence.retention_years')),
        ]);
        $contract->invitations()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $audit->record($contract, 'contract.voided', ['reason' => $data['reason']], $request, 'admin', $request->user()->id);
        $contract->refresh();
        $evidence->refreshPackage($contract);

        return back()->with('success', 'Contratto annullato senza eliminare le prove.');
    }

    public function legalHold(Contract $contract, Request $request, AuditService $audit)
    {
        $contract->update(['legal_hold' => ! $contract->legal_hold]);
        $audit->record($contract, 'contract.legal_hold_changed', ['enabled' => $contract->legal_hold], $request, 'admin', $request->user()->id);

        return back()->with('success', 'Conservazione aggiornata.');
    }
}

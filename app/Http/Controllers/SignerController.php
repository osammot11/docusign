<?php

namespace App\Http\Controllers;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Invitation;
use App\Models\Signature;
use App\Services\AuditService;
use App\Services\DocumentVault;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SignerController extends Controller
{
    public function claim(string $token, Request $request, AuditService $audit)
    {
        $inv = Invitation::where('token_hash', hash('sha256', $token))->with('contract')->firstOrFail();
        abort_unless($inv->isUsable(), 410);
        $sessionToken = bin2hex(random_bytes(32));
        $request->session()->put('signer', ['invitation_id' => $inv->id, 'session_token' => $sessionToken]);
        $request->session()->regenerate();
        $inv->update(['session_hash' => hash('sha256', $sessionToken), 'claimed_at' => $inv->claimed_at ?? now()]);
        $audit->record($inv->contract, 'invitation.claimed', [], $request, 'client');

        return redirect()->route('signer.show');
    }

    public function show(Request $request, AuditService $audit)
    {
        [$inv,$contract] = $this->context($request);
        if ($contract->expires_at->isPast() && ! $contract->status->isTerminal()) {
            $contract->update(['status' => ContractStatus::Expired, 'retention_until' => now()->addYears(config('evidence.retention_years'))]);
            $audit->record($contract, 'contract.expired', [], $request, 'client');
        }if (in_array($contract->status, [ContractStatus::Sent, ContractStatus::Viewed])) {
            $contract->update(['status' => ContractStatus::Viewed]);
            $audit->record($contract, 'document.viewed', [], $request, 'client');
        }

        return view('signer.show', compact('contract', 'inv'));
    }

    public function document(Request $request, DocumentVault $vault)
    {
        [, $contract] = $this->context($request);

        return response($vault->get($contract->source_path), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="documento.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    public function requestOtp(Request $request, OtpService $otp, AuditService $audit)
    {
        [, $contract] = $this->context($request);
        abort_unless(in_array($contract->status, [ContractStatus::Sent, ContractStatus::Viewed]), 409);
        $data = $request->validate(['name' => 'required|string|max:180', 'role' => 'required|string|max:180', 'document_consent' => 'accepted', 'authority_consent' => 'accepted', 'electronic_consent' => 'accepted', 'privacy_acknowledged' => 'accepted']);
        $request->session()->put('signer.confirmation', $data);
        $challenge = $otp->issue($contract, $request);
        $audit->record($contract, 'otp.issued', ['challenge_id' => $challenge->id, 'email' => $contract->signer_email], $request, 'client');

        return redirect()->to(route('signer.show').'#otp')
            ->with('success', 'Codice inviato. Controlla la tua email.');
    }

    public function complete(Request $request, OtpService $otp, AuditService $audit)
    {
        [, $contract] = $this->context($request);
        $request->validate(['otp' => 'required|digits:6']);
        $confirmation = $request->session()->get('signer.confirmation');
        abort_unless($confirmation, 422);
        $challenge = $otp->consume($contract, $request->otp);
        DB::transaction(function () use ($contract, $confirmation, $challenge, $request, $audit) {
            $c = Contract::lockForUpdate()->findOrFail($contract->id);
            abort_unless(in_array($c->status, [ContractStatus::Sent, ContractStatus::Viewed]), 409);
            Signature::create(['contract_id' => $c->id, 'party' => 'client', 'name' => $confirmation['name'], 'role' => $confirmation['role'], 'email' => $c->signer_email, 'consents' => ['document' => true, 'authority' => true, 'electronic_process' => true, 'privacy_notice' => true], 'otp_challenge_id' => $challenge->id, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'signed_at' => now()]);
            $c->update(['status' => ContractStatus::ClientSigned, 'client_signed_at' => now()]);
            $audit->record($c, 'signature.client_completed', ['otp_challenge_id' => $challenge->id, 'source_hash' => $c->source_hash], $request, 'client');
        });
        $request->session()->forget('signer.confirmation');

        return redirect()->route('signer.show')->with('success', 'Firma cliente registrata. Produce a Value deve ora controfirmare.');
    }

    public function decline(Request $request, AuditService $audit)
    {
        [, $contract] = $this->context($request);
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        abort_unless(in_array($contract->status, [ContractStatus::Sent, ContractStatus::Viewed]), 409);
        $contract->update(['status' => ContractStatus::Declined, 'retention_until' => now()->addYears(config('evidence.retention_years'))]);
        $contract->invitations()->update(['revoked_at' => now()]);
        $audit->record($contract, 'contract.declined', ['reason' => $data['reason']], $request, 'client');

        return back()->with('success', 'Rifiuto registrato.');
    }

    public function package(Request $request, DocumentVault $vault)
    {
        [, $contract] = $this->context($request);
        abort_unless($contract->status === ContractStatus::Completed && $contract->package_path, 404);

        return response($vault->get($contract->package_path), 200, ['Content-Type' => 'application/zip', 'Content-Disposition' => 'attachment; filename="fascicolo-'.$contract->public_id.'.zip"']);
    }

    private function context(Request $request): array
    {
        $data = $request->session()->get('signer');
        abort_unless($data, 403);
        $inv = Invitation::with('contract')->findOrFail($data['invitation_id']);
        abort_unless($inv->session_hash && hash_equals($inv->session_hash, hash('sha256', $data['session_token'])), 403);

        return [$inv, $inv->contract];
    }
}

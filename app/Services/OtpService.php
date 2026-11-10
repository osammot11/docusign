<?php

namespace App\Services;

use App\Mail\OtpCodeMail;
use App\Models\Contract;
use App\Models\OtpChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(private TrackedMailer $mailer) {}

    public function issue(Contract $contract, Request $request): OtpChallenge
    {
        $key = 'otp:'.hash('sha256', strtolower($contract->signer_email).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['otp' => 'Troppi codici richiesti. Riprova più tardi.']);
        }
        RateLimiter::hit($key, 3600);
        $last = OtpChallenge::where('contract_id', $contract->id)->latest()->first();
        if ($last && $last->created_at->gt(now()->subSeconds(60))) {
            throw ValidationException::withMessages(['otp' => 'Attendi 60 secondi prima di richiedere un nuovo codice.']);
        }
        OtpChallenge::where('contract_id', $contract->id)->whereNull('consumed_at')->update(['consumed_at' => now()]);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $challenge = OtpChallenge::create(['contract_id' => $contract->id, 'email' => $contract->signer_email, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10), 'requested_ip' => $request->ip()]);
        $this->mailer->send($contract, $contract->signer_email, 'otp', new OtpCodeMail($contract, $code));

        return $challenge;
    }

    public function consume(Contract $contract, string $code): OtpChallenge
    {
        $challenge = OtpChallenge::where('contract_id', $contract->id)->whereNull('consumed_at')->latest()->first();
        if (! $challenge || $challenge->expires_at->isPast()) {
            throw ValidationException::withMessages(['otp' => 'Il codice è scaduto. Richiedine uno nuovo.']);
        }
        if ($challenge->attempts >= 5) {
            throw ValidationException::withMessages(['otp' => 'Numero massimo di tentativi raggiunto.']);
        }
        $challenge->increment('attempts');
        if (! Hash::check($code, $challenge->code_hash)) {
            throw ValidationException::withMessages(['otp' => 'Codice non valido.']);
        }
        $challenge->update(['consumed_at' => now()]);

        return $challenge;
    }
}

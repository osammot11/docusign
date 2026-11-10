<?php

namespace App\Http\Controllers;

use App\Services\TotpService;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function show(Request $request, TotpService $totp)
    {
        $secret = $request->session()->get('2fa.setup_secret') ?? $totp->secret();
        $request->session()->put('2fa.setup_secret', $secret);
        $uri = $totp->uri($secret, $request->user()->email);
        $qr = (new SvgWriter)->write(new QrCode($uri, size: 220))->getString();

        return view('auth.setup-2fa', compact('secret', 'qr'));
    }

    public function confirm(Request $request, TotpService $totp)
    {
        $request->validate(['code' => 'required|digits:6']);
        $secret = $request->session()->pull('2fa.setup_secret');
        if (! $secret || ! $totp->verify($secret, $request->code)) {
            throw ValidationException::withMessages(['code' => 'Codice non valido.']);
        }$plain = [];
        $hashed = [];
        for ($i = 0; $i < 8; $i++) {
            $code = strtoupper(bin2hex(random_bytes(5)));
            $plain[] = $code;
            $hashed[] = Hash::make($code);
        }$request->user()->update(['two_factor_secret' => $secret, 'recovery_codes' => $hashed, 'two_factor_confirmed_at' => now()]);
        $request->session()->put('2fa.recovery_plain', $plain);

        return redirect()->route('security.recovery');
    }

    public function recovery(Request $request)
    {
        $codes = $request->session()->pull('2fa.recovery_plain');
        abort_unless($codes, 404);

        return view('auth.recovery', compact('codes'));
    }
}

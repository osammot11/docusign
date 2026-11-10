<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::validate($data) || ! ($user = User::where('email', $data['email'])->first())?->is_active) {
            throw ValidationException::withMessages(['email' => 'Credenziali non valide.']);
        } $request->session()->regenerate();
        if ($user->two_factor_confirmed_at) {
            $request->session()->put('auth.pending_user', $user->id);

            return redirect()->route('auth.2fa');
        } Auth::login($user);
        $user->update(['last_login_at' => now()]);

        return redirect()->route('security.2fa');
    }

    public function challengeForm(Request $request)
    {
        abort_unless($request->session()->has('auth.pending_user'), 403);

        return view('auth.challenge');
    }

    public function challenge(Request $request, TotpService $totp, AuditService $audit)
    {
        $request->validate(['code' => 'required|string']);
        $user = User::findOrFail($request->session()->get('auth.pending_user'));
        $valid = $totp->verify($user->two_factor_secret, $request->code);
        if (! $valid) {
            $codes = $user->recovery_codes ?? [];
            foreach ($codes as $i => $hash) {
                if (Hash::check(strtoupper($request->code), $hash)) {
                    unset($codes[$i]);
                    $user->recovery_codes = array_values($codes);
                    $user->save();
                    $valid = true;
                    break;
                }
            }
        } if (! $valid) {
            throw ValidationException::withMessages(['code' => 'Codice non valido.']);
        } Auth::login($user);
        $request->session()->forget('auth.pending_user');
        $request->session()->regenerate();
        $user->update(['last_login_at' => now()]);
        $audit->record(null, 'admin.login', [], $request, 'admin', $user->id);

        return redirect()->intended(route('contracts.index'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContractActionController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\SignerController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::get('/two-factor/challenge', [AuthController::class, 'challengeForm'])->name('auth.2fa');
    Route::post('/two-factor/challenge', [AuthController::class, 'challenge'])->middleware('throttle:5,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/security/two-factor', [TwoFactorController::class, 'show'])->name('security.2fa');
    Route::post('/security/two-factor', [TwoFactorController::class, 'confirm'])->name('security.2fa.confirm');
    Route::get('/security/recovery-codes', [TwoFactorController::class, 'recovery'])->name('security.recovery');
});

Route::middleware(['auth', 'two-factor'])->prefix('admin')->group(function () {
    Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
    Route::get('/contracts/create', [ContractController::class, 'create'])->name('contracts.create');
    Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
    Route::get('/contracts/{contract}', [ContractController::class, 'show'])->name('contracts.show');
    Route::get('/contracts/{contract}/document', [ContractController::class, 'document'])->name('contracts.document');
    Route::get('/contracts/{contract}/package', [ContractController::class, 'package'])->name('contracts.package');
    Route::post('/contracts/{contract}/send', [ContractActionController::class, 'send'])->name('contracts.send');
    Route::post('/contracts/{contract}/countersign', [ContractActionController::class, 'countersign'])->name('contracts.countersign');
    Route::post('/contracts/{contract}/void', [ContractActionController::class, 'void'])->name('contracts.void');
    Route::post('/contracts/{contract}/legal-hold', [ContractActionController::class, 'legalHold'])->name('contracts.legal-hold');
});

Route::get('/sign/{token}', [SignerController::class, 'claim'])->where('token', '[a-f0-9]{64}')->name('signer.claim');
Route::get('/sign', [SignerController::class, 'show'])->name('signer.show');
Route::get('/sign/document', [SignerController::class, 'document'])->name('signer.document');
Route::post('/sign/otp', [SignerController::class, 'requestOtp'])->middleware('throttle:10,1')->name('signer.otp');
Route::post('/sign/complete', [SignerController::class, 'complete'])->middleware('throttle:10,1')->name('signer.complete');
Route::post('/sign/decline', [SignerController::class, 'decline'])->name('signer.decline');
Route::get('/sign/package', [SignerController::class, 'package'])->name('signer.package');

Route::get('/verify/{publicId}', [VerificationController::class, 'show'])->name('verify.show');
Route::post('/verify/{publicId}', [VerificationController::class, 'check'])->middleware('throttle:20,1')->name('verify.check');
Route::view('/privacy', 'privacy')->name('privacy');

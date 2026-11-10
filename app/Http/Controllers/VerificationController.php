<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Services\CanonicalJson;
use App\Services\DocumentVault;
use App\Services\EvidenceSigner;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function show(string $publicId)
    {
        $contract = Contract::where('public_id', $publicId)->first();

        return view('verify.show', compact('contract'));
    }

    public function check(string $publicId, Request $request, DocumentVault $vault, EvidenceSigner $signer)
    {
        $contract = Contract::where('public_id', $publicId)->firstOrFail();
        $request->validate(['document' => 'nullable|file|max:51200']);
        $manifestValid = false;
        if ($contract->manifest_path) {
            $manifest = json_decode($vault->get($contract->manifest_path), true, flags: JSON_THROW_ON_ERROR);
            $sig = $manifest['ed25519']['signature'] ?? '';
            unset($manifest['ed25519']);
            $manifestValid = $signer->verify(CanonicalJson::encode($manifest), $sig);
        }$uploadedHash = $request->hasFile('document') ? hash_file('sha256', $request->file('document')->getRealPath()) : null;
        $hashValid = $uploadedHash ? hash_equals((string) $contract->final_pdf_hash, $uploadedHash) : null;

        return view('verify.show', compact('contract', 'manifestValid', 'uploadedHash', 'hashValid'));
    }
}

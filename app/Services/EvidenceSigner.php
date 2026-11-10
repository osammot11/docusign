<?php

namespace App\Services;

use RuntimeException;

class EvidenceSigner
{
    private function pair(): array
    {
        $secret = config('evidence.signing_secret_key');
        $public = config('evidence.signing_public_key');
        if ($secret && $public) {
            return [base64_decode($secret, true), base64_decode($public, true)];
        }
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Chiavi Ed25519 non configurate.');
        }
        $seed = hash('sha256', (string) config('app.key').'evidence', true);
        $pair = sodium_crypto_sign_seed_keypair($seed);

        return [sodium_crypto_sign_secretkey($pair), sodium_crypto_sign_publickey($pair)];
    }

    public function sign(string $data): string
    {
        [$secret] = $this->pair();

        return base64_encode(sodium_crypto_sign_detached($data, $secret));
    }

    public function verify(string $data, string $signature): bool
    {
        [, $public] = $this->pair();

        return sodium_crypto_sign_verify_detached(base64_decode($signature, true), $data, $public);
    }

    public function publicKey(): string
    {
        [, $public] = $this->pair();

        return base64_encode($public);
    }

    public function fingerprint(): string
    {
        [, $public] = $this->pair();

        return hash('sha256', $public);
    }
}

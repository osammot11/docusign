<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateEvidenceKeys extends Command
{
    protected $signature = 'evidence:key:generate';

    protected $description = 'Genera chiavi vault e Ed25519 da copiare nel secret store';

    public function handle(): int
    {
        $pair = sodium_crypto_sign_keypair();
        $this->line('EVIDENCE_VAULT_KEY='.base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
        $this->line('EVIDENCE_SIGNING_SECRET_KEY='.base64_encode(sodium_crypto_sign_secretkey($pair)));
        $this->line('EVIDENCE_SIGNING_PUBLIC_KEY='.base64_encode(sodium_crypto_sign_publickey($pair)));
        $this->warn('Salva queste chiavi fuori dalla VPS. Non saranno mostrate di nuovo.');

        return self::SUCCESS;
    }
}

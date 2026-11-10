<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DocumentVault
{
    private function key(): string
    {
        $encoded = config('evidence.vault_key');
        $key = $encoded ? base64_decode($encoded, true) : false;
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            if (! app()->environment(['local', 'testing'])) {
                throw new RuntimeException('EVIDENCE_VAULT_KEY non configurata.');
            }
            $key = hash('sha256', (string) config('app.key'), true);
        }

        return $key;
    }

    public function put(string $path, string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $this->key());
        Storage::disk('local')->put($path, 'PAV1'.$nonce.$cipher);
        sodium_memzero($plain);

        return $path;
    }

    public function get(string $path): string
    {
        $blob = Storage::disk('local')->get($path);
        if (! str_starts_with($blob, 'PAV1')) {
            throw new RuntimeException('Formato archivio non valido.');
        }
        $nonce = substr($blob, 4, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($blob, 4 + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key());
        if ($plain === false) {
            throw new RuntimeException('Documento non autenticato o chiave errata.');
        }

        return $plain;
    }

    public function delete(string $path): void
    {
        Storage::disk('local')->delete($path);
    }
}

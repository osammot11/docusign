<?php

declare(strict_types=1);

function canonical(mixed $value): mixed
{
    if (! is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map('canonical', $value);
    }
    ksort($value, SORT_STRING);

    return array_map('canonical', $value);
}

if (! extension_loaded('sodium')) {
    fwrite(STDERR, "ERRORE: estensione PHP sodium non disponibile.\n");
    exit(2);
}

$directory = $argv[1] ?? __DIR__;
$manifestPath = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'manifest.json';
$pdfPath = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'final.pdf';
if (! is_file($manifestPath) || ! is_file($pdfPath)) {
    fwrite(STDERR, "ERRORE: manifest.json e final.pdf devono essere nella stessa cartella.\n");
    exit(2);
}

$manifest = json_decode(file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
$ed25519 = $manifest['ed25519'] ?? null;
unset($manifest['ed25519']);
$canonical = json_encode(canonical($manifest), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
$signatureValid = is_array($ed25519)
    && sodium_crypto_sign_verify_detached(base64_decode($ed25519['signature'], true), $canonical, base64_decode($ed25519['public_key'], true));
$expectedHash = $manifest['contract']['final_sha256'] ?? '';
$actualHash = hash_file('sha256', $pdfPath);
$hashValid = hash_equals($expectedHash, $actualHash);

echo 'Firma manifest: '.($signatureValid ? 'VALIDA' : 'NON VALIDA').PHP_EOL;
echo 'Hash PDF finale: '.($hashValid ? 'COINCIDE' : 'NON COINCIDE').PHP_EOL;
echo 'SHA-256: '.$actualHash.PHP_EOL;
exit($signatureValid && $hashValid ? 0 : 1);

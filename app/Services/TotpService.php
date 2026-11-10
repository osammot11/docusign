<?php

namespace App\Services;

class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function secret(): string
    {
        $raw = random_bytes(20);
        $bits = '';
        foreach (str_split($raw) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public function uri(string $secret, string $email): string
    {
        $issuer = rawurlencode(config('app.name'));

        return "otpauth://totp/{$issuer}:".rawurlencode($email)."?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    public function verify(string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $counter = intdiv(time(), 30);
        for ($offset = -1; $offset <= 1; $offset++) {
            if (hash_equals($this->code($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function code(string $secret, int $counter): string
    {
        $key = $this->decode($secret);
        $bin = pack('N2', intdiv($counter, 0x100000000), $counter % 0x100000000);
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[19]) & 15;
        $number = (unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF) % 1000000;

        return str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }

    private function decode(string $encoded): string
    {
        $bits = '';
        foreach (str_split(strtoupper($encoded)) as $char) {
            $pos = strpos(self::ALPHABET, $char);
            if ($pos !== false) {
                $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
            }
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}

<?php

namespace Tests\Unit;

use App\Services\CanonicalJson;
use App\Services\EvidenceSigner;
use App\Services\TotpService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CryptographyTest extends TestCase
{
    #[Test]
    public function canonical_json_is_stable(): void
    {
        $this->assertSame('{"a":{"x":1,"z":2},"b":1}', CanonicalJson::encode(['b' => 1, 'a' => ['z' => 2, 'x' => 1]]));
    }

    #[Test]
    public function evidence_signatures_detect_tampering(): void
    {
        $signer = app(EvidenceSigner::class);
        $signature = $signer->sign('manifest');
        $this->assertTrue($signer->verify('manifest', $signature));
        $this->assertFalse($signer->verify('changed', $signature));
        $this->assertSame(64, strlen($signer->fingerprint()));
    }

    #[Test]
    public function totp_matches_rfc_6238_vector(): void
    {
        $service = new TotpService;
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $this->assertSame('287082', $service->code($secret, 1));
    }
}

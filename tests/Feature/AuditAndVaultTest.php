<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use App\Services\AuditService;
use App\Services\DocumentVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditAndVaultTest extends TestCase
{
    use RefreshDatabase;

    private function contract(): Contract
    {
        $u = User::factory()->create();

        return Contract::create(['uuid' => (string) Str::uuid(), 'public_id' => 'PAVTEST000000001', 'created_by' => $u->id, 'title' => 'Test', 'company_name' => 'Cliente SRL', 'vat_number' => '12345678901', 'signer_name' => 'Mario Rossi', 'signer_role' => 'CEO', 'signer_email' => 'mario@example.test', 'version' => 1, 'status' => 'draft', 'source_path' => 'x', 'source_hash' => str_repeat('a', 64), 'source_size' => 5, 'expires_at' => now()->addDay()]);
    }

    #[Test]
    public function vault_encrypts_and_authenticates_documents(): void
    {
        $vault = app(DocumentVault::class);
        $vault->put('test/document.enc', 'secret PDF');
        $stored = file_get_contents(storage_path('app/private/test/document.enc'));
        $this->assertStringNotContainsString('secret PDF', $stored);
        $this->assertSame('secret PDF', $vault->get('test/document.enc'));
        $vault->delete('test/document.enc');
    }

    #[Test]
    public function audit_chain_is_linked_and_immutable(): void
    {
        $c = $this->contract();
        $service = app(AuditService::class);
        $one = $service->record($c, 'one', ['a' => 1]);
        $two = $service->record($c, 'two', ['b' => 2]);
        $this->assertSame($one->event_hash, $two->previous_hash);
        $this->expectException(LogicException::class);
        $two->update(['event_type' => 'tampered']);
    }
}

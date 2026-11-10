<?php

namespace Tests\Feature;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\Invitation;
use App\Models\OtpChallenge;
use App\Models\User;
use App\Services\DocumentVault;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SigningFlowTest extends TestCase
{
    use RefreshDatabase;

    private function pdf(): string
    {
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(40, 10, 'Contratto test');

        return $pdf->Output('S');
    }

    #[Test]
    public function complete_b2b_signing_flow_produces_verifiable_package(): void
    {
        Mail::fake();
        $user = User::factory()->create(['password' => 'password', 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
        $response = $this->actingAs($user)->post(route('contracts.store'), ['title' => 'Accordo revenue share', 'company_name' => 'Cliente SRL', 'vat_number' => '12345678901', 'signer_name' => 'Mario Rossi', 'signer_role' => 'Amministratore', 'signer_email' => 'mario@example.test', 'signer_pec' => null, 'expires_at' => now()->addDays(7)->format('Y-m-d H:i:s'), 'document' => UploadedFile::fake()->createWithContent('contract.pdf', $this->pdf())]);
        $contract = Contract::firstOrFail();
        $response->assertRedirect(route('contracts.show', $contract));
        $this->assertSame(ContractStatus::Draft, $contract->status);
        $this->post(route('contracts.send', $contract))->assertRedirect();
        $contract->refresh();
        $this->assertSame(ContractStatus::Sent, $contract->status);
        $knownToken = str_repeat('a', 64);
        $contract->invitations()->update(['revoked_at' => now()]);
        Invitation::create(['contract_id' => $contract->id, 'token_hash' => hash('sha256', $knownToken), 'expires_at' => now()->addDay(), 'last_sent_at' => now()]);
        auth()->logout();
        $this->get(route('signer.claim', $knownToken))->assertRedirect(route('signer.show'));
        $this->get(route('signer.show'))->assertOk();
        $this->post(route('signer.otp'), ['name' => 'Mario Rossi', 'role' => 'Amministratore', 'document_consent' => '1', 'authority_consent' => '1', 'electronic_consent' => '1', 'privacy_acknowledged' => '1'])
            ->assertRedirect(route('signer.show').'#otp');
        $this->get(route('signer.show'))
            ->assertOk()
            ->assertSee('3. Inserisci il codice OTP')
            ->assertSee('name="otp"', false);
        $challenge = OtpChallenge::latest()->firstOrFail();
        $challenge->update(['code_hash' => Hash::make('123456')]);
        $this->post(route('signer.complete'), ['otp' => '123456'])->assertRedirect(route('signer.show'));
        $contract->refresh();
        $this->assertSame(ContractStatus::ClientSigned, $contract->status);
        $this->actingAs($user)->post(route('contracts.countersign', $contract), ['password' => 'password', 'confirm' => '1'])->assertRedirect();
        $contract->refresh();
        $this->assertSame(ContractStatus::Completed, $contract->status);
        $this->assertNotNull($contract->package_path);
        $this->assertSame(64, strlen($contract->final_pdf_hash));
        $this->assertCount(2, $contract->signatures);
        $zipPath = tempnam(sys_get_temp_dir(), 'pav-zip-');
        file_put_contents($zipPath, app(DocumentVault::class)->get($contract->package_path));
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertNotFalse($zip->locateName('manifest.json'));
        $this->assertNotFalse($zip->locateName('verify-evidence.php'));
        $zip->close();
        unlink($zipPath);
        $this->post(route('verify.check', $contract->public_id))->assertOk()->assertSee('Manifest Ed25519 valido');
    }

    #[Test]
    public function expired_or_wrong_invitation_is_rejected(): void
    {
        $this->get('/sign/'.str_repeat('b', 64))->assertNotFound();
    }
}

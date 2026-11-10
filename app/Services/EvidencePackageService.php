<?php

namespace App\Services;

use App\Models\Contract;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Process;
use ZipArchive;

class EvidencePackageService
{
    public function __construct(private DocumentVault $vault, private EvidenceSigner $signer, private AuditService $audit) {}

    public function generate(Contract $contract): void
    {
        $contract->load(['signatures.user', 'auditEvents', 'mailDeliveries', 'pecReceipts']);
        $source = $this->vault->get($contract->source_path);
        $tmp = storage_path('app/private/tmp/'.uniqid('evidence-', true));
        if (! is_dir($tmp) && ! mkdir($tmp, 0700, true) && ! is_dir($tmp)) {
            throw new RuntimeException('Impossibile creare la directory temporanea.');
        }
        try {
            file_put_contents("{$tmp}/original.pdf", $source);
            $qr = (new SvgWriter)->write(new QrCode(route('verify.show', $contract->public_id), size: 180))->getString();
            $certificate = app('dompdf.wrapper')->loadView('pdf.certificate', compact('contract', 'qr'))->setPaper('a4')->output();
            file_put_contents("{$tmp}/certificate.pdf", $certificate);
            $final = $this->merge(["{$tmp}/original.pdf", "{$tmp}/certificate.pdf"]);
            $finalHash = hash('sha256', $final);
            file_put_contents("{$tmp}/final.pdf", $final);

            $manifestData = $this->manifest($contract, $finalHash);
            $canonical = CanonicalJson::encode($manifestData);
            $signature = $this->signer->sign($canonical);
            $manifest = CanonicalJson::encode($manifestData + ['ed25519' => ['signature' => $signature, 'public_key' => $this->signer->publicKey(), 'fingerprint' => $this->signer->fingerprint()]]);
            file_put_contents("{$tmp}/manifest.json", $manifest);
            file_put_contents("{$tmp}/audit-report.txt", $this->auditReport($contract));
            file_put_contents("{$tmp}/VERIFY.txt", "PRODUCE A VALUE SIGN\nCodice: {$contract->public_id}\nVerifica online: ".route('verify.show', $contract->public_id)."\nSHA-256 PDF finale: {$finalHash}\nFingerprint Ed25519: ".$this->signer->fingerprint()."\nLa firma Ed25519 riguarda il JSON canonico ottenuto rimuovendo il campo ed25519 dal manifest.\n");
            copy(resource_path('evidence/verify-evidence.php'), "{$tmp}/verify-evidence.php");
            foreach ($contract->mailDeliveries as $mail) {
                if ($mail->raw_path) {
                    file_put_contents("{$tmp}/email-{$mail->id}.eml", $this->vault->get($mail->raw_path));
                }
            }
            foreach ($contract->pecReceipts as $receipt) {
                file_put_contents("{$tmp}/pec-{$receipt->receipt_type}-{$receipt->id}.eml", $this->vault->get($receipt->raw_path));
            }
            $zipPath = "{$tmp}/fascicolo.zip";
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                throw new RuntimeException('Impossibile creare il fascicolo ZIP.');
            }
            foreach (glob("{$tmp}/*") as $file) {
                if ($file !== $zipPath) {
                    $zip->addFile($file, basename($file));
                }
            }
            $zip->close();

            $base = "contracts/{$contract->uuid}/v{$contract->version}";
            $this->vault->put("{$base}/final.pdf.enc", $final);
            $this->vault->put("{$base}/manifest.json.enc", $manifest);
            $this->vault->put("{$base}/fascicolo.zip.enc", file_get_contents($zipPath));
            $contract->update(['final_pdf_path' => "{$base}/final.pdf.enc", 'final_pdf_hash' => $finalHash, 'manifest_path' => "{$base}/manifest.json.enc", 'manifest_signature' => $signature, 'package_path' => "{$base}/fascicolo.zip.enc"]);
            $this->audit->record($contract, 'evidence.generated', ['source_hash' => $contract->source_hash, 'final_hash' => $finalHash, 'manifest_signature' => $signature]);
        } finally {
            if (is_dir($tmp)) {
                foreach (glob("{$tmp}/*") ?: [] as $file) {
                    @unlink($file);
                }
            }
            @rmdir($tmp);
        }
    }

    /** Rebuild manifest and ZIP after receipts or revocation, keeping the final PDF unchanged. */
    public function refreshPackage(Contract $contract): void
    {
        if (! $contract->final_pdf_path || ! $contract->final_pdf_hash) {
            return;
        }

        $contract->load(['signatures.user', 'auditEvents', 'mailDeliveries', 'pecReceipts']);
        $tmp = storage_path('app/private/tmp/'.uniqid('refresh-', true));
        if (! is_dir($tmp) && ! mkdir($tmp, 0700, true) && ! is_dir($tmp)) {
            throw new RuntimeException('Impossibile creare la directory temporanea.');
        }

        try {
            file_put_contents("{$tmp}/original.pdf", $this->vault->get($contract->source_path));
            file_put_contents("{$tmp}/final.pdf", $this->vault->get($contract->final_pdf_path));
            $manifestData = $this->manifest($contract, $contract->final_pdf_hash);
            $signature = $this->signer->sign(CanonicalJson::encode($manifestData));
            $manifest = CanonicalJson::encode($manifestData + ['ed25519' => ['signature' => $signature, 'public_key' => $this->signer->publicKey(), 'fingerprint' => $this->signer->fingerprint()]]);
            file_put_contents("{$tmp}/manifest.json", $manifest);
            file_put_contents("{$tmp}/audit-report.txt", $this->auditReport($contract));
            file_put_contents("{$tmp}/VERIFY.txt", "PRODUCE A VALUE SIGN\nCodice: {$contract->public_id}\nVerifica online: ".route('verify.show', $contract->public_id)."\nSHA-256 PDF finale: {$contract->final_pdf_hash}\nFingerprint Ed25519: ".$this->signer->fingerprint()."\n");
            copy(resource_path('evidence/verify-evidence.php'), "{$tmp}/verify-evidence.php");
            foreach ($contract->pecReceipts as $receipt) {
                file_put_contents("{$tmp}/pec-{$receipt->receipt_type}-{$receipt->id}.eml", $this->vault->get($receipt->raw_path));
            }

            $zipPath = "{$tmp}/fascicolo.zip";
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
                throw new RuntimeException('Impossibile aggiornare il fascicolo ZIP.');
            }
            foreach (glob("{$tmp}/*") as $file) {
                if ($file !== $zipPath) {
                    $zip->addFile($file, basename($file));
                }
            }
            $zip->close();
            $base = "contracts/{$contract->uuid}/v{$contract->version}";
            $this->vault->put("{$base}/manifest.json.enc", $manifest);
            $this->vault->put("{$base}/fascicolo.zip.enc", file_get_contents($zipPath));
            $contract->update(['manifest_signature' => $signature]);
        } finally {
            if (is_dir($tmp)) {
                foreach (glob("{$tmp}/*") ?: [] as $file) {
                    @unlink($file);
                }
            }
            @rmdir($tmp);
        }
    }

    private function manifest(Contract $c, string $finalHash): array
    {
        return ['schema' => 'pav-sign-evidence/v1', 'contract' => ['uuid' => $c->uuid, 'public_id' => $c->public_id, 'title' => $c->title, 'version' => $c->version, 'status' => $c->status->value, 'company_name' => $c->company_name, 'vat_number' => $c->vat_number, 'source_sha256' => $c->source_hash, 'final_sha256' => $finalHash, 'completed_at' => $c->completed_at?->toIso8601String()], 'signatures' => $c->signatures->map(fn ($s) => ['party' => $s->party, 'name' => $s->name, 'role' => $s->role, 'email' => $s->email, 'consents' => $s->consents, 'signed_at' => $s->signed_at->toIso8601String(), 'ip_address' => $s->ip_address, 'user_agent' => $s->user_agent])->values()->all(), 'events' => $c->auditEvents->map(fn ($e) => ['type' => $e->event_type, 'payload' => $e->payload, 'actor_type' => $e->actor_type, 'ip_address' => $e->ip_address, 'created_at' => $e->created_at->toIso8601String(), 'previous_hash' => $e->previous_hash, 'hash' => $e->event_hash])->values()->all(), 'deliveries' => $c->mailDeliveries->map(fn ($m) => ['channel' => $m->channel, 'kind' => $m->kind, 'recipient' => $m->recipient, 'message_id' => $m->message_id, 'status' => $m->status, 'sent_at' => $m->sent_at?->toIso8601String()])->values()->all(), 'generated_at' => now('UTC')->toIso8601String()];
    }

    private function merge(array $paths): string
    {
        $output = dirname($paths[0]).'/merged.pdf';
        try {
            $process = new Process(array_merge(['qpdf', '--empty', '--pages'], $paths, ['--', $output]));
            $process->run();
            if ($process->isSuccessful() && is_file($output)) {
                return file_get_contents($output);
            }
        } catch (\Throwable) {
            // Fallback portabile per sviluppo e test locali.
        }
        $pdf = new Fpdi;
        foreach ($paths as $path) {
            $count = $pdf->setSourceFile($path);
            for ($i = 1; $i <= $count; $i++) {
                $template = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
            }
        }

        return $pdf->Output('S');
    }

    private function auditReport(Contract $c): string
    {
        $lines = ['PRODUCE A VALUE SIGN — DOSSIER PROBATORIO', "Contratto: {$c->title}", "Codice: {$c->public_id}", "Azienda: {$c->company_name} ({$c->vat_number})", "SHA-256 originale: {$c->source_hash}", ''];
        foreach ($c->auditEvents as $e) {
            $lines[] = $e->created_at->toIso8601String()." | {$e->event_type} | {$e->event_hash}";
        }

        return implode("\n", $lines)."\n";
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\MailDelivery;
use App\Models\PecReceipt;
use App\Services\AuditService;
use App\Services\DocumentVault;
use App\Services\EvidencePackageService;
use Illuminate\Console\Command;

class ImportPecReceipts extends Command
{
    protected $signature = 'pec:import-receipts';

    protected $description = 'Importa ricevute PEC via IMAP e conserva gli EML originali';

    public function handle(DocumentVault $vault, AuditService $audit, EvidencePackageService $evidence): int
    {
        if (! function_exists('imap_open')) {
            $this->error('Estensione PHP IMAP non disponibile.');

            return self::FAILURE;
        }
        $host = env('PEC_IMAP_HOST');
        $user = env('PEC_USERNAME');
        $pass = env('PEC_PASSWORD');
        if (! $host || ! $user || ! $pass) {
            $this->warn('PEC IMAP non configurata.');

            return self::SUCCESS;
        }
        $box = imap_open('{'.$host.':'.env('PEC_IMAP_PORT', 993).'/imap/ssl}INBOX', $user, $pass);
        if (! $box) {
            $this->error('Connessione PEC fallita.');

            return self::FAILURE;
        }

        foreach (imap_search($box, 'UNSEEN') ?: [] as $number) {
            $raw = imap_fetchheader($box, $number).imap_body($box, $number, FT_PEEK);
            preg_match('/X-Riferimento-Message-ID:\s*<?([^>\r\n]+)>?/i', $raw, $match);
            $reference = trim($match[1] ?? '');
            $delivery = MailDelivery::where('channel', 'pec')->where('message_id', $reference)->first();
            if (! $delivery || ! ($contract = Contract::find($delivery->contract_id))) {
                continue;
            }
            $type = str_contains(strtolower($raw), 'avvenuta-consegna') ? 'delivered' : (str_contains(strtolower($raw), 'accettazione') ? 'accepted' : 'other');
            $hash = hash('sha256', $raw);
            $path = "contracts/{$contract->uuid}/pec/{$hash}.eml.enc";
            $vault->put($path, $raw);
            PecReceipt::firstOrCreate(
                ['content_hash' => $hash],
                ['contract_id' => $contract->id, 'mail_delivery_id' => $delivery->id, 'receipt_type' => $type, 'message_id' => $reference, 'raw_path' => $path, 'received_at' => now()]
            );
            $audit->record($contract, 'pec.receipt_imported', ['type' => $type, 'content_hash' => $hash]);
            $contract->refresh();
            $evidence->refreshPackage($contract);
            imap_setflag_full($box, (string) $number, '\\Seen');
        }
        imap_close($box);

        return self::SUCCESS;
    }
}

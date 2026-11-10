<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\DocumentVault;
use Illuminate\Console\Command;

class PurgeExpiredEvidence extends Command
{
    protected $signature = 'evidence:purge-expired {--dry-run}';

    protected $description = 'Elimina fascicoli oltre la conservazione, esclusi i legal hold';

    public function handle(DocumentVault $vault): int
    {
        Contract::where('legal_hold', false)->whereNotNull('retention_until')->where('retention_until', '<=', now())->chunkById(50, function ($items) use ($vault) {
            foreach ($items as $c) {
                $this->line(($this->option('dry-run') ? '[dry-run] ' : '').$c->public_id);
                if ($this->option('dry-run')) {
                    continue;
                }foreach (array_filter([$c->source_path, $c->final_pdf_path, $c->manifest_path, $c->package_path]) as $p) {
                    $vault->delete($p);
                }$c->delete();
            }
        });

        return self::SUCCESS;
    }
}

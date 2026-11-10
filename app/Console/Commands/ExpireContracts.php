<?php

namespace App\Console\Commands;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Services\AuditService;
use Illuminate\Console\Command;

class ExpireContracts extends Command
{
    protected $signature = 'contracts:expire';

    protected $description = 'Chiude gli inviti scaduti';

    public function handle(AuditService $audit): int
    {
        Contract::whereIn('status', [ContractStatus::Sent, ContractStatus::Viewed])->where('expires_at', '<=', now())->chunkById(100, function ($items) use ($audit) {
            foreach ($items as $c) {
                $c->update(['status' => ContractStatus::Expired, 'retention_until' => now()->addYears(config('evidence.retention_years'))]);
                $c->invitations()->whereNull('revoked_at')->update(['revoked_at' => now()]);
                $audit->record($c, 'contract.expired');
            }
        });

        return self::SUCCESS;
    }
}

<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case ClientSigned = 'client_signed';
    case Completed = 'completed';
    case Declined = 'declined';
    case Expired = 'expired';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Bozza', self::Sent => 'Inviato', self::Viewed => 'Visualizzato',
            self::ClientSigned => 'Firmato dal cliente', self::Completed => 'Completato',
            self::Declined => 'Rifiutato', self::Expired => 'Scaduto', self::Voided => 'Annullato',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Declined, self::Expired, self::Voided], true);
    }
}

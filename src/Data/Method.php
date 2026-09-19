<?php

namespace Andreaskviby\Qvickly\Data;

/**
 * Betalsätten och deras koder hos Qvickly. Vilka som faktiskt går att välja
 * styrs av avtalet – getAccountinfo() svarar på vad just ditt konto har.
 */
enum Method: int
{
    case InvoiceFactoring = 1;
    case InvoiceService = 2;
    case PartPayment = 4;
    case Card = 8;
    case Bank = 16;
    case Cash = 32;
    case Swish = 1024;

    public function label(): string
    {
        return match ($this) {
            self::InvoiceFactoring => 'Faktura',
            self::InvoiceService => 'Fakturaservice',
            self::PartPayment => 'Delbetalning',
            self::Card => 'Kort',
            self::Bank => 'Direktbank',
            self::Cash => 'Kontant',
            self::Swish => 'Swish',
        };
    }

    /** Okända koder dyker upp när Qvickly lanserar nya betalsätt. */
    public static function labelFor(int|string $code): string
    {
        return self::tryFrom((int) $code)?->label() ?? "Betalsätt {$code}";
    }
}

<?php

namespace Andreaskviby\Qvickly\Support;

/**
 * Qvickly räknar allt i minsta valutaenhet: 231700 betyder 2 317,00 kr.
 * Allt som lämnar paketet är heltal i öre, aldrig flyttal i kronor.
 */
class Money
{
    /** Kronor till öre. 299 kr blir 29900. */
    public static function ore(int|float|string $kronor): int
    {
        return (int) round((float) $kronor * 100);
    }

    /** Öre till kronor, för presentation. */
    public static function kronor(int|float|string $ore): float
    {
        return round((float) $ore / 100, 2);
    }

    /**
     * Nettobeloppet ur ett bruttobelopp. 299 kr inklusive 25 % moms är
     * 239,20 kr exklusive moms.
     */
    public static function net(int $grossOre, float $taxRate): int
    {
        return (int) round($grossOre / (1 + $taxRate / 100));
    }

    /** Momsen ur ett bruttobelopp. */
    public static function tax(int $grossOre, float $taxRate): int
    {
        return $grossOre - self::net($grossOre, $taxRate);
    }

    /** Bruttobeloppet ur ett nettobelopp. */
    public static function gross(int $netOre, float $taxRate): int
    {
        return (int) round($netOre * (1 + $taxRate / 100));
    }

    /** Formaterat belopp för mejl och kvitton: "2 317,00 kr". */
    public static function format(int $ore, string $currency = 'kr'): string
    {
        return trim(number_format(self::kronor($ore), 2, ',', ' ').' '.$currency);
    }
}

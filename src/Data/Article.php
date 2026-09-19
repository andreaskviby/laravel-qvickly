<?php

namespace Andreaskviby\Qvickly\Data;

use Andreaskviby\Qvickly\Support\Money;
use Illuminate\Contracts\Support\Arrayable;

/**
 * En rad i betalningen. Qvickly vill ha styckpris och radsummor exklusive
 * moms, i öre – svenska butiker prissätter nästan alltid inklusive moms, så
 * det vanliga fallet är fromGross().
 *
 * @implements Arrayable<string, mixed>
 */
class Article implements Arrayable
{
    protected function __construct(
        public readonly string $title,
        public readonly float $quantity,
        public readonly int $unitPriceNetOre,
        public readonly int $totalNetOre,
        public readonly int $totalTaxOre,
        public readonly float $taxRate,
        public readonly ?string $artnr = null,
        public readonly float $discountPercent = 0,
    ) {}

    /**
     * Styckpriset är angivet inklusive moms – det normala i en svensk butik.
     */
    public static function fromGross(
        string $title,
        int $unitPriceGrossOre,
        float $quantity = 1,
        float $taxRate = 25,
        ?string $artnr = null,
        float $discountPercent = 0,
    ): self {
        $lineGross = (int) round($unitPriceGrossOre * $quantity * (1 - $discountPercent / 100));

        return new self(
            title: $title,
            quantity: $quantity,
            unitPriceNetOre: Money::net($unitPriceGrossOre, $taxRate),
            totalNetOre: Money::net($lineGross, $taxRate),
            totalTaxOre: Money::tax($lineGross, $taxRate),
            taxRate: $taxRate,
            artnr: $artnr,
            discountPercent: $discountPercent,
        );
    }

    /**
     * Styckpriset är angivet exklusive moms.
     */
    public static function fromNet(
        string $title,
        int $unitPriceNetOre,
        float $quantity = 1,
        float $taxRate = 25,
        ?string $artnr = null,
        float $discountPercent = 0,
    ): self {
        $lineNet = (int) round($unitPriceNetOre * $quantity * (1 - $discountPercent / 100));

        return new self(
            title: $title,
            quantity: $quantity,
            unitPriceNetOre: $unitPriceNetOre,
            totalNetOre: $lineNet,
            totalTaxOre: (int) round($lineNet * $taxRate / 100),
            taxRate: $taxRate,
            artnr: $artnr,
            discountPercent: $discountPercent,
        );
    }

    public function totalGrossOre(): int
    {
        return $this->totalNetOre + $this->totalTaxOre;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'artnr' => (string) ($this->artnr ?? ''),
            'title' => $this->title,
            'quantity' => $this->quantity == (int) $this->quantity
                ? (string) (int) $this->quantity
                : (string) $this->quantity,
            'aprice' => (string) $this->unitPriceNetOre,
            'tax' => (string) $this->totalTaxOre,
            'discount' => (string) $this->discountPercent,
            'withouttax' => (string) $this->totalNetOre,
            'taxrate' => (string) $this->taxRate,
        ];
    }
}

<?php

namespace Andreaskviby\Qvickly\Data;

use Andreaskviby\Qvickly\Support\Money;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Bygger data-objektet till addPayment() och initCheckout(). Summorna räknas
 * fram ur raderna, så att moms och totaler alltid går ihop – det är den
 * vanligaste orsaken till att ett anrop nekas.
 *
 * @implements Arrayable<string, mixed>
 */
class Payment implements Arrayable
{
    /** @var array<int, Article> */
    protected array $articles = [];

    /** @var array<string, mixed> */
    protected array $paymentData = [];

    /** @var array<string, mixed> */
    protected array $paymentInfo = [];

    /** @var array<string, mixed> */
    protected array $customer = [];

    /** @var array<string, mixed> */
    protected array $checkoutData = [];

    protected int $shippingGrossOre = 0;

    protected float $shippingTaxRate = 25;

    protected int $handlingGrossOre = 0;

    protected float $handlingTaxRate = 25;

    protected int $roundingOre = 0;

    public static function make(): self
    {
        return new self;
    }

    public function orderId(string $orderId): self
    {
        $this->paymentData['orderid'] = $orderId;

        return $this;
    }

    public function currency(string $currency): self
    {
        $this->paymentData['currency'] = $currency;

        return $this;
    }

    public function language(string $language): self
    {
        $this->paymentData['language'] = $language;

        return $this;
    }

    public function country(string $country): self
    {
        $this->paymentData['country'] = $country;

        return $this;
    }

    public function method(Method|int $method): self
    {
        $this->paymentData['method'] = (string) ($method instanceof Method ? $method->value : $method);

        return $this;
    }

    public function paymentPlanId(int|string $id): self
    {
        $this->paymentData['paymentplanid'] = (string) $id;

        return $this;
    }

    /**
     * Aktiverar betalningen direkt när den genomförts, i stället för att du
     * måste aktivera den själv vid leverans.
     */
    public function autoActivate(bool $autoActivate = true): self
    {
        $this->paymentData['autoactivate'] = $autoActivate ? '1' : '0';

        return $this;
    }

    public function urls(?string $accept = null, ?string $cancel = null, ?string $callback = null): self
    {
        if ($accept !== null) {
            $this->paymentData['accepturl'] = $accept;
        }

        if ($cancel !== null) {
            $this->paymentData['cancelurl'] = $cancel;
        }

        if ($callback !== null) {
            $this->paymentData['callbackurl'] = $callback;
        }

        return $this;
    }

    public function logo(string $url): self
    {
        $this->paymentData['logo'] = $url;

        return $this;
    }

    /** GET eller POST tillbaka till accepturl. */
    public function returnMethod(string $method): self
    {
        $this->paymentData['returnmethod'] = $method;

        return $this;
    }

    public function reference(?string $yours = null, ?string $ours = null): self
    {
        if ($yours !== null) {
            $this->paymentInfo['yourreference'] = $yours;
        }

        if ($ours !== null) {
            $this->paymentInfo['ourreference'] = $ours;
        }

        return $this;
    }

    public function projectName(string $name): self
    {
        $this->paymentInfo['projectname'] = $name;

        return $this;
    }

    public function deliveryMethod(string $method): self
    {
        $this->paymentInfo['deliverymethod'] = $method;

        return $this;
    }

    /** Fritt fält som följer med betalningen hela vägen till kvittot. */
    public function comment(string $comment): self
    {
        $this->paymentInfo['comment'] = $comment;

        return $this;
    }

    public function article(Article $article): self
    {
        $this->articles[] = $article;

        return $this;
    }

    /**
     * @param  iterable<Article>  $articles
     */
    public function articles(iterable $articles): self
    {
        foreach ($articles as $article) {
            $this->article($article);
        }

        return $this;
    }

    /** Frakt angiven inklusive moms. */
    public function shipping(int $grossOre, float $taxRate = 25): self
    {
        $this->shippingGrossOre = $grossOre;
        $this->shippingTaxRate = $taxRate;

        return $this;
    }

    /** Expeditionsavgift angiven inklusive moms. */
    public function handling(int $grossOre, float $taxRate = 25): self
    {
        $this->handlingGrossOre = $grossOre;
        $this->handlingTaxRate = $taxRate;

        return $this;
    }

    /** Öresavrundning, om butiken avrundar totalen. */
    public function rounding(int $ore): self
    {
        $this->roundingOre = $ore;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $billing
     * @param  array<string, mixed>  $shipping
     */
    public function customer(
        ?string $email = null,
        ?string $phone = null,
        ?string $pno = null,
        ?string $number = null,
        array $billing = [],
        array $shipping = [],
    ): self {
        if ($number !== null) {
            $this->customer['nr'] = $number;
        }

        if ($pno !== null) {
            $this->customer['pno'] = $pno;
        }

        if ($email !== null) {
            $billing['email'] = $billing['email'] ?? $email;
        }

        if ($phone !== null) {
            $billing['phone'] = $billing['phone'] ?? $phone;
        }

        if ($billing !== []) {
            $this->customer['Billing'] = array_merge($this->customer['Billing'] ?? [], $billing);
        }

        if ($shipping !== []) {
            $this->customer['Shipping'] = array_merge($this->customer['Shipping'] ?? [], $shipping);
        }

        return $this;
    }

    /** Villkor och integritetspolicy visas i den hostade kassan. */
    public function terms(string $termsUrl, ?string $privacyPolicyUrl = null): self
    {
        $this->checkoutData['terms'] = $termsUrl;

        if ($privacyPolicyUrl !== null) {
            $this->checkoutData['privacyPolicy'] = $privacyPolicyUrl;
        }

        return $this;
    }

    /** Låter kunden handla som företag i den hostade kassan. */
    public function allowCompany(bool $allow = true): self
    {
        $this->checkoutData['companyView'] = $allow ? 'true' : 'false';

        return $this;
    }

    public function requirePhone(bool $required = true): self
    {
        $this->checkoutData['showPhoneOnDelivery'] = $required ? 'true' : 'false';

        return $this;
    }

    /**
     * Skickar tillbaka kunden till accepturl när betalningen är klar.
     *
     * Utan den här stannar kunden kvar i Qvicklys kassa efter att ha betalat –
     * deras dokumentation säger "needs to be set if you have no custom Thank
     * you page", och standardvärdet är false.
     */
    public function redirectOnSuccess(bool $redirect = true): self
    {
        $this->checkoutData['redirectOnSuccess'] = $redirect ? 'true' : 'false';

        return $this;
    }

    /** Summan kunden ska betala, inklusive moms, frakt och avgifter. */
    public function totalGrossOre(): int
    {
        $articles = array_sum(array_map(fn (Article $a) => $a->totalGrossOre(), $this->articles));

        return $articles + $this->shippingGrossOre + $this->handlingGrossOre + $this->roundingOre;
    }

    /** @return array<string, mixed> */
    protected function cart(): array
    {
        $net = array_sum(array_map(fn (Article $a) => $a->totalNetOre, $this->articles))
            + Money::net($this->shippingGrossOre, $this->shippingTaxRate)
            + Money::net($this->handlingGrossOre, $this->handlingTaxRate);

        $gross = $this->totalGrossOre();

        $cart = [
            'Total' => [
                'rounding' => (string) $this->roundingOre,
                'withouttax' => (string) $net,
                // Momsen räknas som mellanskillnaden, aldrig som en egen
                // avrundning – då kan totalen inte glida isär med ett öre.
                'tax' => (string) ($gross - $this->roundingOre - $net),
                'withtax' => (string) $gross,
            ],
        ];

        if ($this->shippingGrossOre !== 0) {
            $cart['Shipping'] = [
                'withouttax' => (string) Money::net($this->shippingGrossOre, $this->shippingTaxRate),
                'taxrate' => (string) $this->shippingTaxRate,
            ];
        }

        if ($this->handlingGrossOre !== 0) {
            $cart['Handling'] = [
                'withouttax' => (string) Money::net($this->handlingGrossOre, $this->handlingTaxRate),
                'taxrate' => (string) $this->handlingTaxRate,
            ];
        }

        return $cart;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'PaymentData' => $this->paymentData,
            'Articles' => array_map(fn (Article $article) => $article->toArray(), $this->articles),
            'Cart' => $this->cart(),
        ];

        if ($this->paymentInfo !== []) {
            $data['PaymentInfo'] = $this->paymentInfo;
        }

        if ($this->customer !== []) {
            $data['Customer'] = $this->customer;
        }

        if ($this->checkoutData !== []) {
            $data['CheckoutData'] = $this->checkoutData;
        }

        return $data;
    }
}

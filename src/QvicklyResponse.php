<?php

namespace Andreaskviby\Qvickly;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

/**
 * Svaret från ett anrop. Data-objektet är det intressanta – credentials
 * innehåller bara Qvicklys egen signatur och loggid.
 *
 * @implements Arrayable<string, mixed>
 * @implements ArrayAccess<string, mixed>
 */
class QvicklyResponse implements Arrayable, ArrayAccess
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $function,
        public readonly array $payload,
    ) {}

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->payload['data'] ?? [];
    }

    /** Hämtar ur data-objektet med punktnotation: get('PaymentData.number'). */
    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->data(), $key, $default);
    }

    /** Betalningsnumret hos Qvickly – nyckeln till allt senare underhåll. */
    public function number(): ?string
    {
        $number = $this->get('number') ?? $this->get('PaymentData.number');

        return $number === null ? null : (string) $number;
    }

    /** Adressen till den hostade kassan, från initCheckout. */
    public function url(): ?string
    {
        $url = $this->get('url') ?? $this->get('PaymentData.url');

        return $url === null ? null : (string) $url;
    }

    /**
     * Created, WaitingForPurchase, Pending, Paid, Factoring, Service eller
     * Cancelled. Den hostade kassan svarar WaitingForPurchase tills kunden
     * har betalat.
     */
    public function status(): ?string
    {
        $status = $this->get('status') ?? $this->get('PaymentData.status');

        return $status === null ? null : (string) $status;
    }

    public function isPaid(): bool
    {
        return in_array($this->status(), ['Paid', 'Factoring', 'Service'], true);
    }

    /**
     * Betalningen är påbörjad men inte genomförd. Numret från initCheckout är
     * tillfälligt så länge det här gäller – getPaymentinfo() hittar det inte
     * förrän kunden betalat, och det riktiga betalningsnumret kommer i
     * callbacken.
     */
    public function isWaiting(): bool
    {
        return in_array($this->status(), ['Created', 'WaitingForPurchase'], true);
    }

    public function isCancelled(): bool
    {
        return $this->status() === 'Cancelled';
    }

    /** Betalsättets kod, t.ex. 8 för kort. */
    public function method(): ?string
    {
        $method = $this->get('method') ?? $this->get('PaymentData.method');

        return $method === null ? null : (string) $method;
    }

    public function orderId(): ?string
    {
        $orderId = $this->get('orderid') ?? $this->get('PaymentData.orderid');

        return $orderId === null ? null : (string) $orderId;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->payload;
    }

    public function json(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        return (string) json_encode($this->payload, $flags);
    }

    public function offsetExists(mixed $offset): bool
    {
        return Arr::has($this->data(), (string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Ett svar från Qvickly kan inte ändras.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Ett svar från Qvickly kan inte ändras.');
    }
}

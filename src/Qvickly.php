<?php

namespace Andreaskviby\Qvickly;

use Andreaskviby\Qvickly\Data\Payment;
use Andreaskviby\Qvickly\Exceptions\QvicklyException;
use Andreaskviby\Qvickly\Support\Signature;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Klienten mot Qvicklys API (tidigare Billmate). Varje anrop signeras med
 * kontots hemliga nyckel, och varje callback verifieras med samma signatur.
 *
 * Alla belopp är heltal i öre – se Support\Money.
 */
class Qvickly
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config,
        protected ?HttpFactory $http = null,
    ) {
        $this->http = $http ?? new HttpFactory;
    }

    /**
     * En kopia av klienten med andra inställningar, t.ex. testläge:
     * Qvickly::withOptions(['test' => true])->getAccountinfo().
     *
     * @param  array<string, mixed>  $options
     */
    public function withOptions(array $options): self
    {
        return new self(array_merge($this->config, $options), $this->http);
    }

    public function test(bool $test = true): self
    {
        return $this->withOptions(['test' => $test]);
    }

    public function isTest(): bool
    {
        return (bool) ($this->config['test'] ?? false);
    }

    /** En tom betalning att bygga vidare på, med kontots standardvärden ifyllda. */
    public function payment(): Payment
    {
        return Payment::make()
            ->currency((string) ($this->config['currency'] ?? 'SEK'))
            ->language((string) ($this->config['language'] ?? 'sv'))
            ->country((string) ($this->config['country'] ?? 'SE'))
            // Kunden ska tillbaka till butiken när betalningen är klar. Utan
            // den här blir hen kvar i Qvicklys kassa – stäng av med
            // ->redirectOnSuccess(false) om du har en egen tacksida där.
            ->redirectOnSuccess();
    }

    // ────────────────────────────── Betalningar ──────────────────────────────

    /** Skapar en betalning utan hostad kassa – kunduppgifterna måste finnas. */
    public function addPayment(array|Arrayable $data): QvicklyResponse
    {
        return $this->call('addPayment', $data);
    }

    /** Ändrar en betalning. Går bara så länge status är Created. */
    public function updatePayment(array|Arrayable $data): QvicklyResponse
    {
        return $this->call('updatePayment', $data);
    }

    /** Aktiverar betalningen för utbetalning, normalt vid leverans. */
    public function activatePayment(int|string $number, array $extra = []): QvicklyResponse
    {
        return $this->call('activatePayment', ['number' => (string) $number] + $extra);
    }

    public function cancelPayment(int|string $number): QvicklyResponse
    {
        return $this->call('cancelPayment', ['number' => (string) $number]);
    }

    /**
     * Krediterar en betalning – pengarna går tillbaka samma väg som de kom,
     * och kreditfakturor aktiveras alltid automatiskt hos Qvickly.
     *
     * Till skillnad från de andra anropen vill creditPayment ha numret inuti
     * PaymentData, inte löst i data.
     *
     * @param  array<string, mixed>|Arrayable<string, mixed>  $lines  Rader och summor, bara vid delkreditering
     */
    public function creditPayment(int|string $number, array|Arrayable $lines = [], bool $partial = false): QvicklyResponse
    {
        $lines = $lines instanceof Arrayable ? $lines->toArray() : $lines;

        $lines['PaymentData'] = array_merge($lines['PaymentData'] ?? [], [
            'number' => (string) $number,
            'partcredit' => $partial ? 'true' : 'false',
        ]);

        return $this->call('creditPayment', $lines);
    }

    /** Hela betalningen tillbaka till kunden. */
    public function creditFull(int|string $number): QvicklyResponse
    {
        return $this->creditPayment($number);
    }

    /**
     * Delkreditering: skicka raderna som ska betalas tillbaka, enklast byggda
     * med payment() – summorna måste gå ihop precis som vid ett köp.
     *
     * @param  array<string, mixed>|Arrayable<string, mixed>  $lines
     */
    public function creditPartial(int|string $number, array|Arrayable $lines): QvicklyResponse
    {
        return $this->creditPayment($number, $lines, true);
    }

    public function getPaymentinfo(int|string $number): QvicklyResponse
    {
        return $this->call('getPaymentinfo', ['number' => (string) $number]);
    }

    /** Startar den hostade kassan och ger tillbaka en url att visa kunden. */
    public function initCheckout(array|Arrayable $data): QvicklyResponse
    {
        $data = $data instanceof Arrayable ? $data->toArray() : $data;

        // Qvickly visar checkoutdata på toppnivå i sina JSON-exempel men inuti
        // data i sina kodexempel. Vi skickar det på båda ställena: toppnivån
        // ligger utanför signaturen, så det kostar ingenting att vara säker.
        $extra = isset($data['CheckoutData'])
            ? ['checkoutdata' => $data['CheckoutData']]
            : [];

        return $this->call('initCheckout', $data, $extra);
    }

    // ────────────────────────────── Uppslag ──────────────────────────────

    /** Adress och namn ur person- eller organisationsnummer. */
    public function getAddress(string $pno, ?string $country = null): QvicklyResponse
    {
        return $this->call('getAddress', array_filter([
            'pno' => $pno,
            'country' => $country ?? ($this->config['country'] ?? null),
        ]));
    }

    /** Delbetalningsplaner och månadskostnader. */
    public function getPaymentplans(array $data = []): QvicklyResponse
    {
        return $this->call('getPaymentplans', $data);
    }

    public function getTerms(array $data = []): QvicklyResponse
    {
        return $this->call('getTerms', $data);
    }

    /** Kontot och vilka betalsätt som faktiskt är påslagna. */
    public function getAccountinfo(array $data = []): QvicklyResponse
    {
        return $this->call('getAccountinfo', $data);
    }

    /** Förfallna fakturor. */
    public function getDuePayments(array $data = []): QvicklyResponse
    {
        return $this->call('getDuePayments', $data);
    }

    // ────────────────────────────── Callbacks ──────────────────────────────

    /**
     * Verifierar att en callback verkligen kommer från Qvickly. Signaturen
     * räknas över data-objektet med kontots nyckel.
     *
     * @param  Request|array<string, mixed>  $payload
     */
    public function verifyCallback(Request|array $payload, ?string $rawData = null): bool
    {
        $payload = $payload instanceof Request ? $this->callbackPayload($payload) : $payload;

        $hash = (string) Arr::get($payload, 'credentials.hash', '');
        $data = Arr::get($payload, 'data', []);

        if (! is_array($data)) {
            return false;
        }

        if ($rawData !== null && Signature::verifyRaw($rawData, $hash, $this->secret())) {
            return true;
        }

        return Signature::verify($data, $hash, $this->secret());
    }

    /**
     * Läser en callback oavsett om den kommer som JSON eller som formulärdata.
     *
     * @return array<string, mixed>
     */
    public function callbackPayload(Request $request): array
    {
        $json = $request->json()->all();

        if ($json !== [] && (isset($json['data']) || isset($json['credentials']))) {
            return $json;
        }

        return $request->all();
    }

    /**
     * Callbacken säger vad som hänt, men sanningen hämtas alltid hem igen –
     * ett verifierat betalningsnummer är värt mer än ett påstående.
     */
    public function confirmCallback(Request|array $payload): QvicklyResponse
    {
        $payload = $payload instanceof Request ? $this->callbackPayload($payload) : $payload;

        $number = Arr::get($payload, 'data.number') ?? Arr::get($payload, 'data.PaymentData.number');

        if (! $number) {
            throw QvicklyException::fromApi('callback', 'Callbacken saknar betalningsnummer.', 0, $payload);
        }

        return $this->getPaymentinfo((string) $number);
    }

    // ────────────────────────────── Transport ──────────────────────────────

    /**
     * Ett anrop mot API:t. Alla metoder ovan går genom den här.
     *
     * @param  array<string, mixed>|Arrayable<string, mixed>  $data
     * @param  array<string, mixed>  $extraPayload  Fält utanför data, och därmed utanför signaturen
     */
    public function call(string $function, array|Arrayable $data = [], array $extraPayload = []): QvicklyResponse
    {
        $data = $data instanceof Arrayable ? $data->toArray() : $data;

        $payload = $extraPayload + [
            'credentials' => $this->credentials($data),
            // Hashen räknas över exakt den här arrayen – den får inte kodas om
            // på vägen, då stämmer inte signaturen.
            'data' => $data,
            'function' => $function,
        ];

        try {
            $response = $this->http
                ->acceptJson()
                ->asJson()
                ->timeout((int) ($this->config['timeout'] ?? 30))
                ->post($this->endpoint(), $payload);
        } catch (\Throwable $e) {
            throw QvicklyException::transport($function, $e->getMessage());
        }

        $body = $response->json() ?? [];

        if (! $response->successful() && $body === []) {
            throw QvicklyException::transport($function, "HTTP {$response->status()}");
        }

        // Felsvar kommer som {"code": ..., "message": ...} utan data-objekt.
        if (isset($body['code']) && ! isset($body['data'])) {
            throw QvicklyException::fromApi(
                $function,
                (string) ($body['message'] ?? 'Okänt fel'),
                $body['code'],
                $body,
            );
        }

        return new QvicklyResponse($function, $body);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function credentials(array $data): array
    {
        return [
            'id' => $this->id(),
            'hash' => Signature::make($data, $this->secret()),
            'version' => (string) ($this->config['version'] ?? '2.5.0'),
            'client' => 'PHP:Laravel-Qvickly/1.0',
            'serverdata' => $this->serverData(),
            'time' => microtime(true),
            'test' => $this->isTest() ? 'TRUE' : 'FALSE',
            'debug' => ($this->config['debug'] ?? false) ? 'TRUE' : 'FALSE',
            'language' => (string) ($this->config['language'] ?? 'sv'),
            'country' => (string) ($this->config['country'] ?? 'SE'),
        ];
    }

    /**
     * Qvickly loggar var anropet kom ifrån. Utanför en webbförfrågan finns
     * ingen värd att rapportera, och då ska inget krascha.
     *
     * @return array<string, string>
     */
    protected function serverData(): array
    {
        $request = app()->bound('request') ? app('request') : null;

        return [
            'HTTP_HOST' => (string) ($request?->getHost() ?? ''),
            'REMOTE_ADDR' => (string) ($request?->ip() ?? ''),
        ];
    }

    protected function endpoint(): string
    {
        return (string) ($this->config['endpoint'] ?? 'https://api.qvickly.io/');
    }

    public function id(): string
    {
        $id = (string) ($this->config['id'] ?? '');

        if ($id === '') {
            throw QvicklyException::missingCredentials();
        }

        return $id;
    }

    protected function secret(): string
    {
        $secret = (string) ($this->config['secret'] ?? '');

        if ($secret === '') {
            throw QvicklyException::missingCredentials();
        }

        return $secret;
    }
}

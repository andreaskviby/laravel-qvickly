# Laravel Qvickly

Laravel-paket för [Qvickly](https://qvickly.io) (tidigare Billmate): hela betal-API:t, den hostade kassan, betallänkar och verifierade callbacks – med momsmatematiken redan löst.

```php
use Andreaskviby\Qvickly\Data\Article;
use Andreaskviby\Qvickly\Facades\Qvickly;

$checkout = Qvickly::initCheckout(
    Qvickly::payment()
        ->orderId('EK-1009')
        ->article(Article::fromGross('Olivolja 1 L', 29900))
        ->shipping(29900)
        ->urls(
            accept: route('orders.paid', $order),
            cancel: route('orders.show', $order),
            callback: route('webhooks.qvickly'),
        )
        ->terms(route('terms'))
        ->autoActivate()
);

return redirect($checkout->url());
```

## Installation

```bash
composer require andreaskviby/laravel-qvickly
```

Lägg nycklarna i `.env`:

```dotenv
QVICKLY_ID=21912
QVICKLY_SECRET=din-hemliga-nyckel
QVICKLY_TEST=true
```

Konfigurationen kan publiceras, men behövs sällan:

```bash
php artisan vendor:publish --tag=qvickly-config
```

## Belopp

Qvickly räknar i minsta valutaenhet: `29900` betyder 299,00 kr. Paketet gör likadant – allt in och ut är heltal i öre, aldrig flyttal i kronor.

```php
use Andreaskviby\Qvickly\Support\Money;

Money::ore(299);            // 29900
Money::kronor(29900);       // 299.0
Money::net(29900, 25);      // 23920  (exklusive moms)
Money::tax(29900, 25);      // 5980
Money::format(231700);      // "2 317,00 kr"
```

## Rader och moms

Svenska butiker prissätter inklusive moms, Qvickly vill ha radsummor exklusive moms. `Article::fromGross()` räknar om åt dig, och totalerna byggs så att `withouttax + tax + rounding` alltid är exakt `withtax` – den vanligaste orsaken till att ett anrop nekas.

```php
Article::fromGross('Olivolja 1 L', 29900, quantity: 2);          // 299 kr styck inkl. moms
Article::fromGross('Bok', 24900, taxRate: 6);                     // annan momssats
Article::fromNet('Konsultarvode', 120000, taxRate: 25);           // pris exkl. moms
Article::fromGross('Kampanj', 29900, discountPercent: 10);        // radrabatt
```

## Den hostade kassan

`initCheckout` returnerar en adress till Qvicklys kassa, där kunden väljer betalsätt och fyller i sina uppgifter. Visa den i en iframe eller skicka kunden dit.

```php
$checkout = Qvickly::initCheckout(
    Qvickly::payment()
        ->orderId($order->number)
        ->articles($order->lines->map(fn ($line) => Article::fromGross(
            $line->name, Money::ore($line->price), $line->quantity,
        )))
        ->shipping(Money::ore($order->shipping))
        ->urls(accept: ..., cancel: ..., callback: ...)
        ->customer(email: $order->email, phone: $order->phone)
        ->terms(route('terms'), route('privacy'))
        ->allowCompany()
        ->autoActivate()
);

$checkout->url();      // https://checkout.qvickly.io/…
$checkout->number();   // betalningsnumret, spara det på ordern
```

## Kunden ska tillbaka till butiken

`redirectOnSuccess` är påslaget som standard i `Qvickly::payment()`. Utan den
stannar kunden kvar i Qvicklys kassa efter att ha betalat – deras kassa
redirectar inte av sig själv, och det syns inte förrän någon betalat på riktigt.

```php
Qvickly::payment()->redirectOnSuccess(false);   // du har en egen tacksida hos dem
```

Lita ändå aldrig på returen ensam: callbacken kan utebli helt, och kunden kan
stänga fönstret. Stäm av mot `getPaymentinfo()` – se nästa avsnitt.

## Callbacks

Qvickly postar tillbaka när betalningen ändrar status, och försöker om i tre månader tills du svarar 200. Verifiera signaturen – och lita sedan på API:t framför callbacken.

```php
Route::post('/webhooks/qvickly', function (Request $request) {
    abort_unless(Qvickly::verifyCallback($request), 403);

    $payment = Qvickly::confirmCallback($request);   // hämtar hem sanningen

    if ($payment->isPaid()) {
        Order::where('order_number', $payment->orderId())->first()?->markPaid($payment->number());
    }

    return response()->noContent();
})->withoutMiddleware(VerifyCsrfToken::class);
```

`verifyCallback()` klarar både JSON och formulärdata, och jämför tidskonstant.

## Alla funktioner

| Metod | Vad den gör |
| --- | --- |
| `initCheckout($data)` | Startar den hostade kassan, ger en url |
| `addPayment($data)` | Skapar en betalning direkt, utan kassa |
| `updatePayment($data)` | Ändrar en betalning med status `Created` |
| `activatePayment($number)` | Aktiverar för utbetalning, normalt vid leverans |
| `cancelPayment($number)` | Avbryter en skapad betalning |
| `creditPayment($number, $extra)` | Krediterar helt eller delvis |
| `getPaymentinfo($number)` | Hämtar status, kund, rader och summor |
| `getAddress($pno)` | Namn och adress ur person-/organisationsnummer |
| `getPaymentplans($data)` | Delbetalningsplaner och månadskostnader |
| `getTerms($data)` | Betalningsvillkor |
| `getAccountinfo()` | Kontot och vilka betalsätt som är påslagna |
| `getDuePayments()` | Förfallna fakturor |
| `call($function, $data)` | Vilket framtida anrop som helst |

Svaret är ett `QvicklyResponse`:

```php
$payment = Qvickly::getPaymentinfo($number);

$payment->status();                      // Created, Pending, Paid, Factoring, Service, Cancelled
$payment->isPaid();                      // Paid, Factoring eller Service
$payment->number();
$payment->orderId();
$payment->method();                      // "8"
$payment->get('Customer.Billing.email'); // punktnotation ned i svaret
$payment->get('Cart.Total.withtax');
```

Betalsätten har koder, och de har namn:

```php
use Andreaskviby\Qvickly\Data\Method;

Method::Swish->value;             // 1024
Method::labelFor('8');            // "Kort"
```

## Testläge

Testläget är en flagga, inte ett annat konto – samma id och nyckel. I testläge görs ingen riktig kreditupplysning och inga pengar rör sig.

```dotenv
QVICKLY_TEST=true
```

```php
Qvickly::test()->getAccountinfo();        // tvinga testläge för ett anrop
Qvickly::test(false)->getPaymentinfo(1);  // tvinga skarpt läge
```

Kolla vad kontot faktiskt har påslaget innan du bygger vidare:

```php
collect(Qvickly::getAccountinfo()->get('paymentoptions'))
    ->pluck('method')
    ->map(fn ($code) => Method::labelFor($code));
```

## Att testa din egen integration

Paketet går genom Laravels HTTP-klient, så `Http::fake()` räcker. `QvicklyFake` ger färdiga svar och korrekt signerade callbacks:

```php
use Andreaskviby\Qvickly\Testing\QvicklyFake;

Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::checkout('4242'))]);

$this->post('/webhooks/qvickly', QvicklyFake::callback(
    ['number' => '4242', 'status' => 'Paid', 'orderid' => 'EK-1001'],
    config('qvickly.secret'),
))->assertNoContent();
```

## Fel

Allt som går fel blir ett `QvicklyException` med Qvicklys egen felkod och meddelande:

```php
use Andreaskviby\Qvickly\Exceptions\QvicklyException;

try {
    Qvickly::initCheckout($data);
} catch (QvicklyException $e) {
    report($e);            // $e->getCode(), $e->function, $e->payload
}
```

## Krav

PHP 8.2+, Laravel 11, 12 eller 13.

## Bidra

Buggar och förslag är välkomna som issues. Kör `composer test` och `composer format` innan du skickar en pull request.

## Licens

MIT. Se [LICENSE](LICENSE).

Paketet är byggt för och används av [sicilianobyek.se](https://sicilianobyek.se), men har inget med Qvickly AB att göra utöver att prata med deras API.

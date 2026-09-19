<?php

namespace Andreaskviby\Qvickly\Tests;

use Andreaskviby\Qvickly\Data\Article;
use Andreaskviby\Qvickly\Data\Method;
use Andreaskviby\Qvickly\Exceptions\QvicklyException;
use Andreaskviby\Qvickly\Facades\Qvickly;
use Andreaskviby\Qvickly\Support\Money;
use Andreaskviby\Qvickly\Support\Signature;
use Andreaskviby\Qvickly\Testing\QvicklyFake;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class QvicklyTest extends TestCase
{
    public function test_every_call_is_signed_over_the_data_object(): void
    {
        Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::paymentInfo())]);

        Qvickly::getPaymentinfo('1001');

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            $this->assertSame('getPaymentinfo', $body['function']);
            $this->assertSame(['number' => '1001'], $body['data']);
            $this->assertSame('21912', $body['credentials']['id']);
            $this->assertSame('TRUE', $body['credentials']['test']);
            $this->assertSame(
                Signature::make($body['data'], self::SECRET),
                $body['credentials']['hash'],
            );

            return true;
        });
    }

    public function test_the_hash_covers_exactly_what_is_sent(): void
    {
        Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::checkout())]);

        Qvickly::initCheckout(
            Qvickly::payment()
                ->orderId('EK-1001')
                ->article(Article::fromGross('Olivolja 1 L', 29900))
                ->toArray()
        );

        Http::assertSent(function ($request) {
            // Hashen räknas över data-objektet – råkroppen måste innehålla
            // exakt samma JSON som signerades.
            $raw = $request->body();
            $body = json_decode($raw, true);
            $dataJson = json_encode($body['data']);

            $this->assertSame(
                hash_hmac('sha512', $dataJson, self::SECRET),
                $body['credentials']['hash'],
            );

            return true;
        });
    }

    public function test_api_errors_become_exceptions(): void
    {
        Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::error('Invalid credentials', 1101))]);

        $this->expectException(QvicklyException::class);
        $this->expectExceptionMessage('Invalid credentials');

        Qvickly::getAccountinfo();
    }

    public function test_checkout_response_exposes_url_and_number(): void
    {
        Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::checkout('4242'))]);

        $response = Qvickly::initCheckout(['PaymentData' => ['orderid' => 'EK-1']]);

        $this->assertSame('4242', $response->number());
        $this->assertStringContainsString('checkout.qvickly.io/4242', $response->url());
        $this->assertSame('Created', $response->status());
        $this->assertFalse($response->isPaid());
        $this->assertTrue($response->isWaiting());
    }

    public function test_a_checkout_waiting_for_payment_is_not_paid(): void
    {
        Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::checkout('101', 'https://checkout.billmate.se/21912/token'))]);

        // Så här svarar den hostade kassan i verkligheten innan kunden betalat.
        Http::fake(['api.qvickly.io/*' => Http::response([
            'credentials' => ['hash' => 'x'],
            'data' => ['number' => '101', 'status' => 'WaitingForPurchase', 'url' => 'https://checkout.billmate.se/21912/token'],
        ])]);

        $response = Qvickly::initCheckout(['PaymentData' => []]);

        $this->assertTrue($response->isWaiting());
        $this->assertFalse($response->isPaid());
        $this->assertFalse($response->isCancelled());
    }

    public function test_payment_info_reports_paid(): void
    {
        Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::paymentInfo('4242', 'Paid', 'EK-1001', 57900))]);

        $response = Qvickly::getPaymentinfo('4242');

        $this->assertTrue($response->isPaid());
        $this->assertSame('EK-1001', $response->orderId());
        $this->assertSame('57900', $response->get('Cart.Total.withtax'));
        $this->assertSame('Kort', Method::labelFor($response->method()));
    }

    public function test_callbacks_are_verified_and_forgeries_rejected(): void
    {
        $data = ['number' => '4242', 'status' => 'Paid', 'orderid' => 'EK-1001'];

        $genuine = QvicklyFake::callback($data, self::SECRET);
        $forged = ['credentials' => ['hash' => str_repeat('b', 128)], 'data' => $data];

        $this->assertTrue(Qvickly::verifyCallback($genuine));
        $this->assertFalse(Qvickly::verifyCallback($forged));
        $this->assertFalse(Qvickly::verifyCallback(['data' => $data]));
    }

    public function test_callback_is_read_from_both_json_and_form_data(): void
    {
        $data = ['number' => '4242', 'status' => 'Paid'];
        $payload = QvicklyFake::callback($data, self::SECRET);

        $json = Request::create('/callback', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
        $form = Request::create('/callback', 'POST', $payload);

        $this->assertTrue(Qvickly::verifyCallback($json));
        $this->assertTrue(Qvickly::verifyCallback($form));
    }

    public function test_callback_is_confirmed_against_the_api(): void
    {
        Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::paymentInfo('4242', 'Paid'))]);

        $payload = QvicklyFake::callback(['number' => '4242', 'status' => 'Pending'], self::SECRET);

        // Callbacken påstår Pending, API:t säger Paid – API:t vinner alltid.
        $this->assertTrue(Qvickly::confirmCallback($payload)->isPaid());
    }

    public function test_test_mode_can_be_toggled_per_call(): void
    {
        Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::paymentInfo())]);

        Qvickly::test(false)->getPaymentinfo('1');

        Http::assertSent(fn ($request) => json_decode($request->body(), true)['credentials']['test'] === 'FALSE');
    }

    public function test_missing_credentials_are_reported_clearly(): void
    {
        config(['qvickly.secret' => '']);
        app()->forgetInstance(\Andreaskviby\Qvickly\Qvickly::class);

        $this->expectException(QvicklyException::class);
        $this->expectExceptionMessage('QVICKLY_ID och QVICKLY_SECRET');

        app(\Andreaskviby\Qvickly\Qvickly::class)->getAccountinfo();
    }

    public function test_money_converts_between_kronor_and_ore(): void
    {
        $this->assertSame(29900, Money::ore(299));
        $this->assertSame(299.0, Money::kronor(29900));
        $this->assertSame(23920, Money::net(29900, 25));
        $this->assertSame(5980, Money::tax(29900, 25));
        $this->assertSame(29900, Money::gross(23920, 25));
        $this->assertSame('299,00 kr', Money::format(29900));
    }

    public function test_articles_are_priced_from_gross_and_net(): void
    {
        $gross = Article::fromGross('Olivolja 1 L', 29900, 2)->toArray();

        $this->assertSame('23920', $gross['aprice']);
        $this->assertSame('47840', $gross['withouttax']);
        $this->assertSame('11960', $gross['tax']);
        $this->assertSame('25', $gross['taxrate']);

        $net = Article::fromNet('Frakt', 10000, 1, 25)->toArray();

        $this->assertSame('10000', $net['withouttax']);
        $this->assertSame('2500', $net['tax']);
    }

    public function test_the_cart_total_always_adds_up(): void
    {
        $payment = Qvickly::payment()
            ->orderId('EK-1001')
            ->article(Article::fromGross('Olivolja 0,5 L', 15900, 3))
            ->article(Article::fromGross('Pasta', 2900, 2))
            ->shipping(29900)
            ->handling(0);

        $data = $payment->toArray();
        $total = $data['Cart']['Total'];

        $expected = 15900 * 3 + 2900 * 2 + 29900;

        $this->assertSame((string) $expected, $total['withtax']);
        $this->assertSame(
            (int) $total['withtax'],
            (int) $total['withouttax'] + (int) $total['tax'] + (int) $total['rounding'],
        );
        $this->assertSame($expected, $payment->totalGrossOre());
    }

    public function test_the_payment_builder_writes_the_expected_structure(): void
    {
        $data = Qvickly::payment()
            ->orderId('EK-1009')
            ->method(Method::Swish)
            ->autoActivate()
            ->urls(accept: 'https://shop.test/klart', cancel: 'https://shop.test/avbrutet', callback: 'https://shop.test/callback')
            ->reference('EK-1009')
            ->terms('https://shop.test/villkor', 'https://shop.test/integritet')
            ->allowCompany()
            ->customer(email: 'kund@example.com', phone: '070-0000000', billing: ['firstname' => 'Test'])
            ->article(Article::fromGross('Olivolja 2 L', 57900))
            ->toArray();

        $this->assertSame('EK-1009', $data['PaymentData']['orderid']);
        $this->assertSame('1024', $data['PaymentData']['method']);
        $this->assertSame('1', $data['PaymentData']['autoactivate']);
        $this->assertSame('https://shop.test/callback', $data['PaymentData']['callbackurl']);
        $this->assertSame('SEK', $data['PaymentData']['currency']);
        $this->assertSame('EK-1009', $data['PaymentInfo']['yourreference']);
        $this->assertSame('kund@example.com', $data['Customer']['Billing']['email']);
        $this->assertSame('Test', $data['Customer']['Billing']['firstname']);
        $this->assertSame('https://shop.test/villkor', $data['CheckoutData']['terms']);
        $this->assertSame('https://shop.test/integritet', $data['CheckoutData']['privacyPolicy']);
        $this->assertSame('true', $data['CheckoutData']['companyView']);
        // Utan den här stannar kunden kvar hos Qvickly efter betalningen.
        $this->assertSame('true', $data['CheckoutData']['redirectOnSuccess']);
        $this->assertCount(1, $data['Articles']);
    }

    public function test_checkout_data_is_sent_where_qvickly_reads_it(): void
    {
        Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::checkout())]);

        Qvickly::initCheckout(Qvickly::payment()->orderId('EK-1')->terms('https://shop.test/villkor'));

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);

            // Både på toppnivå och i data: deras dokumentation visar båda, och
            // toppnivån ligger utanför signaturen.
            $this->assertSame('true', $body['checkoutdata']['redirectOnSuccess']);
            $this->assertSame('true', $body['data']['CheckoutData']['redirectOnSuccess']);

            // Signaturen räknas fortfarande bara över data.
            $this->assertSame(
                hash_hmac('sha512', json_encode($body['data']), self::SECRET),
                $body['credentials']['hash'],
            );

            return true;
        });
    }

    public function test_a_credit_is_sent_the_way_qvickly_wants_it(): void
    {
        Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::paymentInfo('1071', 'Credited'))]);

        Qvickly::creditFull('1071');

        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true)['data'];

            // Numret ligger i PaymentData här, till skillnad från övriga anrop.
            $this->assertSame('1071', $data['PaymentData']['number']);
            $this->assertSame('false', $data['PaymentData']['partcredit']);

            return true;
        });
    }

    public function test_a_partial_credit_carries_the_lines_back(): void
    {
        Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::paymentInfo('1071', 'Credited'))]);

        Qvickly::creditPartial('1071', Qvickly::payment()->article(
            Article::fromGross('Olivolja 1 L', 29900)
        ));

        Http::assertSent(function ($request) {
            $data = json_decode($request->body(), true)['data'];

            $this->assertSame('true', $data['PaymentData']['partcredit']);
            $this->assertSame('29900', $data['Cart']['Total']['withtax']);
            $this->assertCount(1, $data['Articles']);

            return true;
        });
    }

    public function test_every_documented_function_is_reachable(): void
    {
        Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::paymentInfo())]);

        Qvickly::addPayment(['PaymentData' => []]);
        Qvickly::updatePayment(['PaymentData' => []]);
        Qvickly::activatePayment('1');
        Qvickly::cancelPayment('1');
        Qvickly::creditPayment('1');
        Qvickly::getPaymentinfo('1');
        Qvickly::initCheckout(['PaymentData' => []]);
        Qvickly::getAddress('19860101-0000');
        Qvickly::getPaymentplans();
        Qvickly::getTerms();
        Qvickly::getAccountinfo();
        Qvickly::getDuePayments();

        Http::assertSentCount(12);
    }
}

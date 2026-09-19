<?php

namespace Andreaskviby\Qvickly\Facades;

use Andreaskviby\Qvickly\Data\Payment;
use Andreaskviby\Qvickly\Qvickly as Client;
use Andreaskviby\Qvickly\QvicklyResponse;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Payment payment()
 * @method static QvicklyResponse addPayment(array|\Illuminate\Contracts\Support\Arrayable $data)
 * @method static QvicklyResponse updatePayment(array|\Illuminate\Contracts\Support\Arrayable $data)
 * @method static QvicklyResponse activatePayment(int|string $number, array $extra = [])
 * @method static QvicklyResponse cancelPayment(int|string $number)
 * @method static QvicklyResponse creditPayment(int|string $number, array $extra = [])
 * @method static QvicklyResponse getPaymentinfo(int|string $number)
 * @method static QvicklyResponse initCheckout(array|\Illuminate\Contracts\Support\Arrayable $data)
 * @method static QvicklyResponse getAddress(string $pno, ?string $country = null)
 * @method static QvicklyResponse getPaymentplans(array $data = [])
 * @method static QvicklyResponse getTerms(array $data = [])
 * @method static QvicklyResponse getAccountinfo(array $data = [])
 * @method static QvicklyResponse getDuePayments(array $data = [])
 * @method static QvicklyResponse call(string $function, array|\Illuminate\Contracts\Support\Arrayable $data = [], array $extraPayload = [])
 * @method static bool verifyCallback(\Illuminate\Http\Request|array $payload, ?string $rawData = null)
 * @method static array callbackPayload(\Illuminate\Http\Request $request)
 * @method static QvicklyResponse confirmCallback(\Illuminate\Http\Request|array $payload)
 * @method static Client withOptions(array $options)
 * @method static Client test(bool $test = true)
 * @method static bool isTest()
 *
 * @see Client
 */
class Qvickly extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Client::class;
    }
}

<?php

namespace Andreaskviby\Qvickly\Testing;

use Andreaskviby\Qvickly\Support\Signature;

/**
 * Färdiga svar att mata Http::fake() med, och signerade callbacks att posta
 * mot din egen route. Gör det möjligt att testa hela betalflödet utan att
 * röra Qvicklys servrar.
 *
 * Http::fake(['api.qvickly.io/*' => Http::response(QvicklyFake::checkout())]);
 */
class QvicklyFake
{
    /**
     * Svar från initCheckout.
     *
     * @return array<string, mixed>
     */
    public static function checkout(string $number = '1001', ?string $url = null): array
    {
        return [
            'credentials' => ['hash' => str_repeat('a', 128), 'logid' => 1],
            'data' => [
                'number' => $number,
                'status' => 'Created',
                'url' => $url ?? "https://checkout.qvickly.io/{$number}/token/test",
            ],
        ];
    }

    /**
     * Svar från getPaymentinfo.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function paymentInfo(
        string $number = '1001',
        string $status = 'Paid',
        string $orderId = 'ORDER-1',
        int $totalOre = 29900,
        array $overrides = [],
    ): array {
        return [
            'credentials' => ['hash' => str_repeat('a', 128), 'logid' => 1],
            'data' => array_replace_recursive([
                'number' => $number,
                'status' => $status,
                'orderid' => $orderId,
                'PaymentData' => [
                    'number' => $number,
                    'status' => $status,
                    'orderid' => $orderId,
                    'method' => '8',
                    'currency' => 'SEK',
                    'paymentdate' => date('Y-m-d'),
                ],
                'Cart' => [
                    'Total' => [
                        'withtax' => (string) $totalOre,
                        'withouttax' => (string) ((int) round($totalOre / 1.25)),
                        'tax' => (string) ($totalOre - (int) round($totalOre / 1.25)),
                        'rounding' => '0',
                    ],
                ],
            ], $overrides),
        ];
    }

    /**
     * En korrekt signerad callback, som den hade kommit från Qvickly.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function callback(array $data, string $secret): array
    {
        return [
            'credentials' => [
                'hash' => Signature::make($data, $secret),
                'logid' => 1,
            ],
            'data' => $data,
        ];
    }

    /**
     * Felsvar från API:t.
     *
     * @return array<string, mixed>
     */
    public static function error(string $message = 'Something went wrong', int $code = 1101): array
    {
        return ['code' => $code, 'message' => $message];
    }
}

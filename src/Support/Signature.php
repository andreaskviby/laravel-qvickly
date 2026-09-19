<?php

namespace Andreaskviby\Qvickly\Support;

/**
 * Signaturen: HMAC-SHA512 över JSON-kodningen av data-objektet, med kontots
 * hemliga nyckel. Samma beräkning används åt båda håll – vi signerar våra
 * anrop och verifierar deras callbacks med den.
 */
class Signature
{
    /**
     * @param  array<mixed>  $data
     */
    public static function make(array $data, string $secret): string
    {
        return hash_hmac('sha512', json_encode($data), $secret);
    }

    /**
     * Verifierar en callback. Jämförelsen är tidskonstant.
     *
     * @param  array<mixed>  $data  Data-objektet exakt som det togs emot.
     */
    public static function verify(array $data, string $hash, string $secret): bool
    {
        if ($hash === '') {
            return false;
        }

        return hash_equals(self::make($data, $secret), $hash);
    }

    /**
     * Verifierar mot den råa JSON-strängen i stället för en avkodad array.
     * Använd den här när kroppen kan läsas ordagrant – då kan ingen skillnad
     * i JSON-kodning mellan avsändare och mottagare ställa till det.
     */
    public static function verifyRaw(string $json, string $hash, string $secret): bool
    {
        if ($hash === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $json, $secret), $hash);
    }
}

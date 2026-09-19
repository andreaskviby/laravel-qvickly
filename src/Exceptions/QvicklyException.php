<?php

namespace Andreaskviby\Qvickly\Exceptions;

use RuntimeException;

/**
 * Ett fel från Qvickly – antingen ett transportfel eller ett felsvar från API:t.
 * Felkoden är Qvicklys egen när ett sådant finns.
 */
class QvicklyException extends RuntimeException
{
    /** @var array<string, mixed> */
    public array $payload = [];

    public ?string $function = null;

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromApi(string $function, string $message, int|string $code = 0, array $payload = []): self
    {
        $exception = new self(
            "Qvickly svarade med ett fel på {$function}: {$message}",
            is_numeric($code) ? (int) $code : 0,
        );

        $exception->function = $function;
        $exception->payload = $payload;

        return $exception;
    }

    public static function missingCredentials(): self
    {
        return new self(
            'Qvickly saknar id eller nyckel. Sätt QVICKLY_ID och QVICKLY_SECRET i .env.',
        );
    }

    public static function transport(string $function, string $message): self
    {
        $exception = new self("Qvickly kunde inte nås vid {$function}: {$message}");
        $exception->function = $function;

        return $exception;
    }
}

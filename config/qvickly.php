<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kontouppgifter
    |--------------------------------------------------------------------------
    |
    | Id och nyckel får du av Qvickly när kontot öppnas. Nyckeln signerar varje
    | anrop och verifierar varje callback – den ska aldrig lämna servern.
    |
    */

    'id' => env('QVICKLY_ID'),

    'secret' => env('QVICKLY_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Testläge
    |--------------------------------------------------------------------------
    |
    | I testläge görs ingen riktig kreditupplysning och inga riktiga pengar rör
    | sig. Samma id och nyckel används i båda lägena.
    |
    */

    'test' => (bool) env('QVICKLY_TEST', false),

    'debug' => (bool) env('QVICKLY_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | API
    |--------------------------------------------------------------------------
    */

    'endpoint' => env('QVICKLY_ENDPOINT', 'https://api.qvickly.io/'),

    'version' => '2.5.0',

    'timeout' => (int) env('QVICKLY_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Standardvärden
    |--------------------------------------------------------------------------
    |
    | Används när ett anrop inte anger något annat.
    |
    */

    'currency' => env('QVICKLY_CURRENCY', 'SEK'),

    'language' => env('QVICKLY_LANGUAGE', 'sv'),

    'country' => env('QVICKLY_COUNTRY', 'SE'),

];

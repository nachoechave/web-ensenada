<?php

namespace Tests\Http;

use GuzzleHttp\Client;

final class LocalHttps
{
    public static function client(array $options = []): Client
    {
        return new Client([
            'base_uri' => 'https://localhost:18443',
            'verify' => '/certs/root.crt',
            'curl' => [CURLOPT_RESOLVE => ['localhost:18443:'.gethostbyname('tls')]],
            'http_errors' => false,
            'timeout' => 20,
            'headers' => ['Accept' => 'application/json'],
            ...$options,
        ]);
    }
}

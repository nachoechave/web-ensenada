<?php

namespace Tests\Parity;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Http\LocalHttps;

class NewsSpringParityTest extends TestCase
{
    public static function requests(): array
    {
        return array_map(fn (string $suffix): array => [$suffix], [
            '', '?page=&size=', '?page=-3&size=0', '?page=1&size=48', '?size=999', '?page=20', '?page=2147483647&size=1',
            '?page=1&page=2&size=1', '?page%5B%5D=1&size=1', '?page=0x1&size=1', '?page=1%202&size=1', '?q=ignorado&sort=titulo',
            '?page=foo', '?page=2147483648', '?page=%20', '?page=&page=2&size=1', '?page=1%2C2', '?size=1.5',
            '/destacadas', '/63', '/00063', '/cronica-ensenada-2', '/CRONICA-ENSENADA-2', '/noticia-1',
            '/borrador-privado', '/archivo-privado', '/61', '/62', '/sin-noticia', '/9223372036854775808', '/%27%20OR%201%3D1',
        ]);
    }

    #[DataProvider('requests')]
    public function test_live_spring_and_laravel_return_the_same_json(string $suffix): void
    {
        [$spring, $laravel] = $this->responses($suffix);
        $this->assertSame($spring->getStatusCode(), $laravel->getStatusCode());
        $expected = json_decode((string) $spring->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $actual = json_decode((string) $laravel->getBody(), true, flags: JSON_THROW_ON_ERROR);
        if ($spring->getStatusCode() >= 400) {
            foreach ([$expected, $actual] as $error) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}Z$/', $error['timestamp']);
            }
            unset($expected['timestamp'], $actual['timestamp']);
        }
        $this->assertSame($this->normalize($expected), $this->normalize($actual));
    }

    public function test_a_spring_internal_offset_error_is_a_deliberate_laravel_validation_error(): void
    {
        [$spring, $laravel] = $this->responses('?page=2147483647&size=48');
        $this->assertSame(500, $spring->getStatusCode());
        $this->assertSame(400, $laravel->getStatusCode());
    }

    private function responses(string $suffix): array
    {
        $spring = new Client(['base_uri' => 'http://spring:8080', 'http_errors' => false, 'timeout' => 15,
            'headers' => ['Accept' => 'application/json']]);

        return [$spring->get('/api/noticias'.$suffix), LocalHttps::client()->get('/api/noticias'.$suffix)];
    }

    private function normalize(array $object): array
    {
        if (! array_is_list($object)) {
            ksort($object);
        }

        return array_map(fn (mixed $value): mixed => is_array($value) ? $this->normalize($value) : $value, $object);
    }
}

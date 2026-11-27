<?php

namespace Tests\Unit;

use App\Auth\JavaBcrypt;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class JavaBcryptTest extends TestCase
{
    public static function javaVectors(): array
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/java-bcrypt.json'), true, flags: JSON_THROW_ON_ERROR);

        return array_map(fn (array $vector): array => [$vector['password'], $vector['hash']], $fixture['vectors']);
    }

    #[DataProvider('javaVectors')]
    public function test_php_verifies_actual_spring_security_2a_hashes(string $password, string $hash): void
    {
        $bcrypt = new JavaBcrypt;

        $this->assertTrue($bcrypt->verify($password, $hash));
        $this->assertFalse($bcrypt->verify('Wrong-synthetic-password', $hash));
    }

    public function test_php_generated_hashes_verify_and_reject_truncation(): void
    {
        $bcrypt = new JavaBcrypt;
        $password = str_repeat('a', 72);
        $hash = $bcrypt->hash($password);

        $this->assertTrue($bcrypt->verify($password, $hash));
        $this->assertFalse($bcrypt->verify($password.'b', $hash));
        $this->assertFalse($bcrypt->verify("Synthetic-null\0-password", $hash));
        $this->assertFalse($bcrypt->verify($password, 'not-a-bcrypt-hash'));
    }
}

<?php

namespace App\Auth;

final class JavaBcrypt
{
    private const DUMMY = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';

    public function verify(#[\SensitiveParameter] string $password, ?string $hash): bool
    {
        $validHash = is_string($hash) && preg_match('/^\$2[aby]\$(0[4-9]|[12][0-9]|3[01])\$[.\/A-Za-z0-9]{53}$/D', $hash) === 1;
        $matches = password_verify($password, $validHash ? $hash : self::DUMMY);

        return $validHash && strlen($password) <= 72 && ! str_contains($password, "\0") && $matches;
    }

    public function hash(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    }
}

<?php

declare(strict_types=1);

namespace App\Security;

final class CsrfTokenManager
{
    private const SESSION_KEY = '_csrf_tokens';
    private const TTL = 1800;

    public static function generateToken(string $formId): string
    {
        self::ensureSessionBucket();

        $token = bin2hex(random_bytes(32));

        $_SESSION[self::SESSION_KEY][$formId] = [
            'token' => $token,
            'expires_at' => time() + self::TTL,
        ];

        return $token;
    }

    public static function validateToken(string $formId, ?string $token): bool
    {
        self::ensureSessionBucket();

        $stored = $_SESSION[self::SESSION_KEY][$formId] ?? null;
        unset($_SESSION[self::SESSION_KEY][$formId]);

        if (!$stored || !isset($stored['token'], $stored['expires_at'])) {
            return false;
        }

        if ($stored['expires_at'] < time()) {
            return false;
        }

        return hash_equals($stored['token'], (string) $token);
    }

    private static function ensureSessionBucket(): void
    {
        if (!isset($_SESSION[self::SESSION_KEY]) || !is_array($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
    }
}

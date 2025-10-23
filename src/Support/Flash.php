<?php

declare(strict_types=1);

namespace App\Support;

final class Flash
{
    private const KEY = '_flash';

    public static function init(): void
    {
        if (!isset($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = [
                'current' => [],
                'next' => [],
            ];
        }

        if (!isset($_SESSION[self::KEY]['next'])) {
            $_SESSION[self::KEY]['next'] = [];
        }

        $_SESSION[self::KEY]['current'] = $_SESSION[self::KEY]['next'];
        $_SESSION[self::KEY]['next'] = [];
    }

    public static function push(string $type, string $message): void
    {
        $_SESSION[self::KEY]['next'][$type][] = $message;
    }

    /**
     * @return array<int, string>
     */
    public static function get(string $type): array
    {
        return $_SESSION[self::KEY]['current'][$type] ?? [];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function all(): array
    {
        return $_SESSION[self::KEY]['current'] ?? [];
    }
}

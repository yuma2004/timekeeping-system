<?php

declare(strict_types=1);

namespace App\Support;

final class Config
{
    private static array $values = [];

    public static function init(array $config): void
    {
        self::$values = $config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$values;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function all(): array
    {
        return self::$values;
    }
}

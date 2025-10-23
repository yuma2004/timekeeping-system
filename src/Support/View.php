<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class View
{
    public static function render(string $template, array $data = []): string
    {
        $baseDir = __DIR__ . '/../../templates/';
        $path = $baseDir . $template . '.php';

        if (!is_file($path)) {
            throw new RuntimeException("View {$template} not found.");
        }

        $e = static fn(mixed $value): string => self::escape($value);
        extract($data, EXTR_SKIP);

        ob_start();
        include $path;

        return (string) ob_get_clean();
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

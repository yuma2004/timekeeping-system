<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\View;

final class Response
{
    public static function view(string $template, array $data = [], int $status = 200, array $headers = []): void
    {
        $content = View::render($template, $data);
        self::send($content, $status, array_merge(['Content-Type' => 'text/html; charset=utf-8'], $headers));
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): void
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        self::send($body === false ? '{}' : $body, $status, array_merge(['Content-Type' => 'application/json'], $headers));
    }

    public static function redirect(string $location, int $status = 302): void
    {
        header('Location: ' . $location, true, $status);
        exit;
    }

    public static function text(string $content, int $status = 200, array $headers = []): void
    {
        self::send($content, $status, array_merge(['Content-Type' => 'text/plain; charset=utf-8'], $headers));
    }

    private static function send(string $content, int $status, array $headers): void
    {
        http_response_code($status);

        foreach ($headers as $key => $value) {
            header($key . ': ' . $value);
        }

        echo $content;
        exit;
    }
}

<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $body;
    private array $files;
    private array $server;
    private array $cookies;
    private array $attributes = [];

    private function __construct(
        string $method,
        string $path,
        array $query,
        array $body,
        array $files,
        array $server,
        array $cookies
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->query = $query;
        $this->body = $body;
        $this->files = $files;
        $this->server = $server;
        $this->cookies = $cookies;
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $body = $_POST;

        if (in_array($method, ['POST', 'PUT', 'PATCH']) && str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw ?: '{}', true);
            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        return new self(
            $method,
            $path,
            $_GET,
            $body,
            $_FILES,
            $_SERVER,
            $_COOKIE
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function inputs(): array
    {
        return $this->body;
    }

    public function files(): array
    {
        return $this->files;
    }

    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }

    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookies[$key] ?? $default;
    }

    public function ip(): string
    {
        $forwarded = $this->server('HTTP_X_FORWARDED_FOR');
        if ($forwarded) {
            $parts = explode(',', (string) $forwarded);
            return trim($parts[0]);
        }

        return (string) $this->server('REMOTE_ADDR', '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string) $this->server('HTTP_USER_AGENT', '');
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}

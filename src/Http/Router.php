<?php

declare(strict_types=1);

namespace App\Http;

use Closure;

final class Router
{
    /** @var array<string, array<int, array{regex:string,variables:array<int,string>,handler:callable}>> */
    private array $routes = [];
    private ?Closure $fallback = null;

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, callable $handler): void
    {
        $this->add('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function fallback(callable $handler): void
    {
        $this->fallback = Closure::fromCallable($handler);
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = rtrim($request->path(), '/') ?: '/';

        $candidates = $this->routes[$method] ?? [];

        foreach ($candidates as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                foreach ($route['variables'] as $index => $name) {
                    $request->setAttribute($name, $matches[$index + 1] ?? null);
                }

                $response = ($route['handler'])($request);

                if (is_string($response)) {
                    echo $response;
                }

                return;
            }
        }

        if ($this->fallback instanceof Closure) {
            ($this->fallback)($request);
            return;
        }

        Response::text('not found', 404);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        [$regex, $variables] = $this->compilePattern($pattern);

        $this->routes[strtoupper($method)][] = [
            'regex' => $regex,
            'variables' => $variables,
            'handler' => $handler,
        ];
    }

    /**
     * @return array{string,array<int,string>}
     */
    private function compilePattern(string $pattern): array
    {
        $pattern = rtrim($pattern, '/') ?: '/';
        $variableNames = [];

        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function (array $matches) use (&$variableNames) {
            $variableNames[] = $matches[1];
            return '([^/]+)';
        }, $pattern);

        return ['#^' . $regex . '$#', $variableNames];
    }
}

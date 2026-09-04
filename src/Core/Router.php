<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<int, array{pattern:string, handler:callable|array, middleware:array}>> */
    private array $routes = ['GET' => [], 'POST' => []];
    private array $groupMiddleware = [];

    public function get(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function any(string $pattern, callable|array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /** Aplica una llista de middleware a totes les rutes definides dins del callback. */
    public function group(array $middleware, callable $definitions): void
    {
        $previous = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($previous, $middleware);
        $definitions($this);
        $this->groupMiddleware = $previous;
    }

    private function add(string $method, string $pattern, callable|array $handler, array $middleware): void
    {
        $this->routes[$method][] = [
            'pattern'    => '/' . trim($pattern, '/'),
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = $path === '' ? '/' : $path;
        $method = $method === 'HEAD' ? 'GET' : $method;

        foreach ($this->routes[$method] ?? [] as $route) {
            $params = $this->match($route['pattern'], $path);
            if ($params === null) {
                continue;
            }
            foreach ($route['middleware'] as $mw) {
                $mw();
            }
            $this->invoke($route['handler'], $params);
            return;
        }

        // El camí existeix però amb un altre verb → 405.
        $other = $method === 'GET' ? 'POST' : 'GET';
        foreach ($this->routes[$other] ?? [] as $route) {
            if ($this->match($route['pattern'], $path) !== null) {
                http_response_code(405);
                header('Allow: ' . $other);
                echo 'Mètode no permès';
                return;
            }
        }

        $this->notFound();
    }

    /** @return array<string,string>|null */
    private function match(string $pattern, string $path): ?array
    {
        if (!str_contains($pattern, '{')) {
            return $pattern === $path ? [] : null;
        }
        $names = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            static function (array $m) use (&$names): string {
                $names[] = $m[1];
                return '(' . ($m[2] ?? '[^/]+') . ')';
            },
            $pattern
        );
        if (!preg_match('#^' . $regex . '$#', $path, $matches)) {
            return null;
        }
        array_shift($matches);
        return array_combine($names, array_map('strval', $matches));
    }

    private function invoke(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = is_object($class) ? $class : new $class();
            $instance->$method(...array_values($params));
            return;
        }
        $handler(...array_values($params));
    }

    private function notFound(): void
    {
        http_response_code(404);
        if (Request::wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'not_found']);
            return;
        }
        View::render('web/error', [
            'title'   => 'Pàgina no trobada',
            'code'    => 404,
            'message' => 'La pàgina que cerqueu no existeix o s\'ha mogut.',
        ]);
    }
}

<?php
declare(strict_types=1);

namespace Daybreak;

/**
 * Minimal method+path router. Static paths and {param} segments.
 * Handlers are [ControllerClass::class, 'method'].
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,vars:string[],handler:array}> */
    private array $routes = [];

    public function add(string $method, string $path, array $handler): void
    {
        $vars  = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_]+)\}#', static function (array $m) use (&$vars): string {
            $vars[] = $m[1];
            return '([^/]+)';
        }, $path);
        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'vars'    => $vars,
            'handler' => $handler,
        ];
    }

    public function get(string $p, array $h): void  { $this->add('GET', $p, $h); }
    public function post(string $p, array $h): void { $this->add('POST', $p, $h); }

    public function dispatch(string $method, string $path): void
    {
        $path = rtrim($path, '/') ?: '/';
        foreach ($this->routes as $r) {
            if ($r['method'] !== strtoupper($method)) {
                continue;
            }
            if (preg_match($r['regex'], $path, $m)) {
                $args = [];
                foreach ($r['vars'] as $i => $name) {
                    $args[$name] = $m[$i + 1];
                }
                [$class, $fn] = $r['handler'];
                (new $class())->{$fn}($args);
                return;
            }
        }
        http_response_code(404);
    }
}

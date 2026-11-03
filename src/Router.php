<?php
declare(strict_types=1);

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function get(string $p, callable $h): void  { $this->add('GET', $p, $h); }
    public function post(string $p, callable $h): void { $this->add('POST', $p, $h); }

    public function dispatch(string $method, string $uri): void
    {
        $path = rtrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/') ?: '/';
        foreach ($this->routes as [$m, $regex, $handler]) {
            if ($m !== $method) {
                continue;
            }
            if (preg_match($regex, $path, $mm)) {
                $params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler(...array_map('rawurldecode', $params));
                return;
            }
        }
        http_response_code(404);
        View::render('errors/404', ['title' => 'Not found']);
    }
}

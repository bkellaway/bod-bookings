<?php
declare(strict_types=1);

final class Env
{
    private static array $vars = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            // strip inline comments on unquoted values
            if ($v !== '' && $v[0] !== '"' && $v[0] !== "'") {
                $v = trim(preg_replace('/\s+#.*$/', '', $v));
            }
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
                $v = substr($v, 1, -1);
            }
            self::$vars[$k] = $v;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $v = self::$vars[$key] ?? getenv($key);
        return ($v === false || $v === null || $v === '') ? $default : (string)$v;
    }

    public static function require(string $key): string
    {
        $v = self::get($key);
        if ($v === null) {
            throw new RuntimeException("Missing required env var $key");
        }
        return $v;
    }

    public static function int(string $key, int $default): int
    {
        return (int)(self::get($key) ?? $default);
    }
}

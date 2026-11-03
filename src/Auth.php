<?php
declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;

    public static function user(): ?array
    {
        if (self::$user === null && !empty($_SESSION['uid'])) {
            self::$user = Models::user((int)$_SESSION['uid']);
        }
        return self::$user;
    }

    public static function require(): array
    {
        $u = self::user();
        if (!$u) {
            $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/app';
            redirect('/login');
        }
        return $u;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $userId;
        self::$user = null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
        self::$user = null;
    }

    public static function refresh(): void
    {
        self::$user = null;
    }
}

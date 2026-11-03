<?php
declare(strict_types=1);

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_url(string $path = ''): string
{
    return rtrim(Env::require('APP_URL'), '/') . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function redirect(string $to): never
{
    header('Location: ' . (str_starts_with($to, 'http') ? $to : app_url($to)));
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    return $_SESSION['csrf'] ??= Crypto::token(16);
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        exit('Bad request (CSRF)');
    }
}

function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}

function json_in(): array
{
    static $cached = null;
    if ($cached === null) {
        $d = json_decode(file_get_contents('php://input') ?: '', true);
        $cached = is_array($d) ? $d : [];
    }
    return $cached;
}

function valid_email(string $e): bool
{
    return (bool)filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 254;
}

function valid_tz(string $tz): bool
{
    return in_array($tz, DateTimeZone::listIdentifiers(), true);
}

function fmt_when(int $ts, string $tz): string
{
    $z = valid_tz($tz) ? new DateTimeZone($tz) : new DateTimeZone('UTC');
    return (new DateTimeImmutable('@' . $ts))->setTimezone($z)->format('l, F j, Y \a\t g:i A T');
}

function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-');
}

function plan_limit_event_types(array $user): ?int
{
    return $user['plan'] === 'pro' && in_array($user['plan_status'], ['active', 'trialing', 'past_due'], true)
        ? null
        : Env::int('FREE_EVENT_TYPES', 1);
}

function is_pro(array $user): bool
{
    return plan_limit_event_types($user) === null;
}

function log_error(string $msg): void
{
    error_log('[bookings] ' . $msg);
}

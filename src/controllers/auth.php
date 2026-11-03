<?php
declare(strict_types=1);

function auth_login(): void
{
    if (Auth::user()) redirect('/app');
    View::render('login', ['title' => 'Sign in']);
}

function auth_google_start(): void
{
    $_SESSION['oauth_state'] = Crypto::token(16);
    $hint = Auth::user()['email'] ?? null;   // reconnect flow: hint the same account
    redirect(GoogleCalendar::forApp()->authUrl($_SESSION['oauth_state'], $hint));
}

function auth_google_callback(): void
{
    if (isset($_GET['error'])) {
        flash('err', 'Google sign-in was cancelled.');
        redirect(Auth::user() ? '/app/settings' : '/login');
    }
    $state = (string)($_GET['state'] ?? '');
    if (!hash_equals($_SESSION['oauth_state'] ?? '', $state)) {
        flash('err', 'Sign-in session expired. Please try again.');
        redirect('/login');
    }
    unset($_SESSION['oauth_state']);

    $g   = GoogleCalendar::forApp();
    $tok = $g->exchangeCode((string)($_GET['code'] ?? ''));
    $info = $g->userInfo($tok['access_token']);

    if (empty($info['sub']) || empty($info['email'])) {
        flash('err', 'Google did not return an account.');
        redirect('/login');
    }
    $scope = $tok['scope'] ?? '';
    $hasCalendar = str_contains($scope, 'calendar.events');

    $current = Auth::user();
    $user = Models::userByGoogleSub($info['sub']) ?? Models::userByEmail($info['email']);

    if ($current && $user && (int)$user['id'] !== (int)$current['id']) {
        flash('err', 'That Google account belongs to a different ' . Env::get('APP_NAME') . ' user. Sign out first.');
        redirect('/app/settings');
    }
    if (!$user) {
        $tz = $_COOKIE['tz'] ?? 'America/Los_Angeles';
        if (!valid_tz($tz)) $tz = 'America/Los_Angeles';
        $id = Models::createUser($info['email'], (string)($info['name'] ?? ''), $info['sub'], $tz);
        $user = Models::user($id);
        $isNew = true;
    } else {
        $isNew = false;
        if ($user['google_sub'] !== $info['sub']) {
            Models::updateUser((int)$user['id'], ['google_sub' => $info['sub']]);
        }
    }

    if (!empty($tok['refresh_token'])) {
        Models::saveToken((int)$user['id'], $tok['refresh_token'], $tok['access_token'], time() + (int)($tok['expires_in'] ?? 3600), $scope);
    } elseif (!Models::calendarConnected((int)$user['id'])) {
        // Google only returns refresh_token on first consent (or with prompt=consent, which we send). Fallback message:
        flash('err', 'Google did not issue a calendar token. Remove this app at myaccount.google.com/permissions and connect again.');
    }
    if (!$hasCalendar) {
        flash('err', 'Calendar permission was not granted — bookings will not work until you reconnect and allow calendar access.');
    }

    Auth::login((int)$user['id']);
    if ($isNew) {
        flash('ok', 'Welcome! Set your availability and create your first event type.');
        redirect('/app/event-types');
    }
    $after = $_SESSION['after_login'] ?? '/app';
    unset($_SESSION['after_login']);
    redirect(str_starts_with($after, '/app') ? $after : '/app');
}

function auth_logout(): void
{
    csrf_check();
    Auth::logout();
    redirect('/');
}

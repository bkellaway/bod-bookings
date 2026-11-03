<?php
declare(strict_types=1);

error_reporting(E_ALL);
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

define('BASE_PATH', dirname(__DIR__));

require __DIR__ . '/Env.php';
require __DIR__ . '/DB.php';
require __DIR__ . '/Crypto.php';
require __DIR__ . '/Router.php';
require __DIR__ . '/View.php';
require __DIR__ . '/GoogleCalendar.php';
require __DIR__ . '/Availability.php';
require __DIR__ . '/Stripe.php';
require __DIR__ . '/Mailer.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/Auth.php';
require __DIR__ . '/Models.php';
require __DIR__ . '/Booking.php';

Env::load(BASE_PATH . '/.env');
ini_set('display_errors', Env::get('APP_ENV') === 'local' ? '1' : '0');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => str_starts_with(Env::get('APP_URL', ''), 'https://'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('bod_session');

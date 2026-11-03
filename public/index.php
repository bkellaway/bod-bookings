<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

// Stripe webhooks carry no session/CSRF; everything else gets a session.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if ($path !== '/stripe/webhook') {
    session_start();
}

// Every page is dynamic (sessions, live availability). Tell Varnish/CDNs never to cache it.
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');

foreach (glob(BASE_PATH . '/src/controllers/*.php') as $f) {
    require $f;
}

$r = new Router();

// marketing / legal
$r->get('/',        'page_home');
$r->get('/pricing', 'page_pricing');
$r->get('/privacy', 'page_privacy');
$r->get('/terms',   'page_terms');

// auth
$r->get('/login',          'auth_login');
$r->get('/auth/google',    'auth_google_start');
$r->get('/auth/callback',  'auth_google_callback');
$r->post('/logout',        'auth_logout');

// dashboard
$r->get('/app',                        'app_dashboard');
$r->get('/app/event-types',            'app_event_types');
$r->post('/app/event-types',           'app_event_types_save');
$r->post('/app/event-types/delete',    'app_event_types_delete');
$r->get('/app/availability',           'app_availability');
$r->post('/app/availability',          'app_availability_save');
$r->get('/app/settings',               'app_settings');
$r->post('/app/settings',              'app_settings_save');
$r->post('/app/calendar/disconnect',   'app_calendar_disconnect');
$r->post('/app/bookings/cancel',       'app_booking_cancel');
$r->get('/app/billing',                'app_billing');
$r->post('/app/billing/checkout',      'app_billing_checkout');
$r->post('/app/billing/portal',        'app_billing_portal');
$r->post('/app/account/delete',        'app_account_delete');

// stripe
$r->post('/stripe/webhook', 'stripe_webhook');

// public booking API + manage
$r->get('/api/slots',        'api_slots');
$r->post('/api/book',        'api_book');
$r->get('/api/booking',      'api_booking');
$r->post('/api/reschedule',  'api_reschedule');
$r->post('/api/cancel',      'api_cancel');
$r->get('/m/{token}',        'page_manage');

// public profile + booking page (last: catch-all patterns)
$r->get('/{username}',        'page_profile');
$r->get('/{username}/{slug}', 'page_book');

try {
    $r->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $e) {
    log_error(get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (str_starts_with($path, '/api/')) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unexpected error.']);
    } else {
        View::render('errors/500', ['title' => 'Something went wrong', 'detail' => Env::get('APP_ENV') === 'local' ? $e->getMessage() : null]);
    }
}

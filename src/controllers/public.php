<?php
declare(strict_types=1);

function page_profile(string $username): void
{
    $u = Models::userByUsername($username);
    if (!$u) { http_response_code(404); View::render('errors/404', ['title' => 'Not found']); return; }
    View::render('public/profile', [
        'title' => $u['name'], 'host' => $u,
        'types' => Models::eventTypes((int)$u['id'], true),
        'connected' => Models::calendarConnected((int)$u['id']),
    ], 'public_layout');
}

function page_book(string $username, string $slug): void
{
    $u = Models::userByUsername($username);
    $t = $u ? Models::eventTypeBySlug((int)$u['id'], $slug) : null;
    if (!$u || !$t) { http_response_code(404); View::render('errors/404', ['title' => 'Not found']); return; }
    View::render('public/book', [
        'title' => $t['name'] . ' — ' . $u['name'], 'host' => $u, 'type' => $t,
        'connected' => Models::calendarConnected((int)$u['id']),
    ], 'public_layout');
}

function page_manage(string $token): void
{
    $b = Models::bookingByToken($token);
    $u = $b ? Models::user((int)$b['user_id']) : null;
    View::render('public/manage', ['title' => 'Your booking', 'booking' => $b, 'host' => $u, 'token' => $token], 'public_layout');
}

// ---------- JSON API ----------

function api_host_and_type(): array
{
    $u = Models::userByUsername((string)($_GET['u'] ?? json_in()['u'] ?? ''));
    if (!$u) json_out(['error' => 'Unknown host'], 404);
    if (!Models::calendarConnected((int)$u['id'])) json_out(['error' => 'This host has not connected a calendar yet.'], 503);
    $slug = (string)($_GET['type'] ?? json_in()['type'] ?? '');
    $t = Models::eventTypeBySlug((int)$u['id'], $slug);
    if (!$t) json_out(['error' => 'Unknown event type'], 404);
    return [$u, $t];
}

function api_guard(callable $fn): void
{
    try {
        $fn();
    } catch (SlotUnavailable $e) {
        json_out(['error' => $e->getMessage()], 409);
    } catch (GoogleApiException $e) {
        log_error($e->getMessage());
        json_out(['error' => 'Calendar service error. Please try again.'], 502);
    }
}

/** GET /api/slots?u=&type=&from=YYYY-MM-DD&to=YYYY-MM-DD */
function api_slots(): void
{
    api_guard(function () {
        if (!empty($_GET['t'])) {                 // reschedule flow: host + duration come from the booking
            [$u, $b] = api_load_managed();
            if (!Models::calendarConnected((int)$u['id'])) json_out(['error' => 'Host calendar is disconnected.'], 503);
            $t = ['duration' => $b['duration']];
        } else {
            [$u, $t] = api_host_and_type();
        }
        $tz = new DateTimeZone($u['timezone']);
        $mk = fn(string $ymd) => DateTimeImmutable::createFromFormat('Y-m-d', $ymd, $tz) ?: null;
        $from = $mk((string)($_GET['from'] ?? date('Y-m-d')));
        $to   = $mk((string)($_GET['to'] ?? date('Y-m-d', strtotime('+1 month'))));
        if (!$from || !$to) json_out(['error' => 'Bad date'], 400);
        $fromTs = $from->setTime(0, 0)->modify('-1 day')->getTimestamp();
        $toTs   = $to->setTime(23, 59, 59)->modify('+1 day')->getTimestamp();
        if ($toTs - $fromTs > 45 * 86400) json_out(['error' => 'Range too large'], 400);

        $av = Booking::availability($u);
        [$wf, $wt] = $av->window();
        $fromTs = max($fromTs, $wf);
        $toTs   = min($toTs, $wt);
        $slots  = $fromTs < $toTs ? $av->slots((int)$t['duration'], $fromTs, $toTs, Booking::busy($u, $fromTs, $toTs)) : [];
        json_out(['slots' => array_map(fn($s) => gmdate('c', $s), $slots), 'window' => [gmdate('c', $wf), gmdate('c', $wt)], 'duration' => (int)$t['duration']]);
    });
}

/** POST /api/book {u, type, start, name, email, notes, tz, website} */
function api_book(): void
{
    api_guard(function () {
        [$u, $t] = api_host_and_type();
        $in    = json_in();
        if (!empty($in['website'])) json_out(['error' => 'Rejected'], 400);   // honeypot
        $name  = trim((string)($in['name'] ?? ''));
        $email = trim((string)($in['email'] ?? ''));
        $notes = trim((string)($in['notes'] ?? ''));
        $tz    = (string)($in['tz'] ?? '');
        $start = strtotime((string)($in['start'] ?? '')) ?: 0;
        if ($name === '' || mb_strlen($name) > 120) json_out(['error' => 'Please enter your name'], 422);
        if (!valid_email($email))                   json_out(['error' => 'Please enter a valid email'], 422);
        if (mb_strlen($notes) > 2000)               json_out(['error' => 'Notes are too long'], 422);
        if (!$start)                                json_out(['error' => 'Please pick a time'], 422);
        if (!valid_tz($tz)) $tz = $u['timezone'];

        $b = Booking::create($u, $t, $start, $name, $email, $notes, $tz);
        json_out(['ok' => true, 'booking' => Booking::view($b, $u)], 201);
    });
}

function api_load_managed(): array
{
    $token = (string)($_GET['t'] ?? json_in()['t'] ?? '');
    $b = $token !== '' ? Models::bookingByToken($token) : null;
    if (!$b) json_out(['error' => 'Invalid or expired link'], 404);
    $u = Models::user((int)$b['user_id']);
    if ($b['status'] === 'cancelled') json_out(['error' => 'This booking was cancelled.', 'cancelled' => true, 'booking' => Booking::view($b, $u)], 410);
    return [$u, $b];
}

function api_booking(): void
{
    [$u, $b] = api_load_managed();
    json_out(['booking' => Booking::view($b, $u), 'type' => Models::eventType((int)$u['id'], (int)$b['event_type_id'])['slug'] ?? null]);
}

function api_reschedule(): void
{
    api_guard(function () {
        [$u, $b] = api_load_managed();
        if (!Models::calendarConnected((int)$u['id'])) json_out(['error' => 'Host calendar is disconnected.'], 503);
        $start = strtotime((string)(json_in()['start'] ?? '')) ?: 0;
        if (!$start) json_out(['error' => 'Please pick a time'], 422);
        $b = Booking::reschedule($u, $b, $start);
        json_out(['ok' => true, 'booking' => Booking::view($b, $u)]);
    });
}

function api_cancel(): void
{
    api_guard(function () {
        [$u, $b] = api_load_managed();
        Booking::cancel($u, $b, 'guest');
        json_out(['ok' => true]);
    });
}

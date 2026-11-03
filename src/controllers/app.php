<?php
declare(strict_types=1);

function app_dashboard(): void
{
    $u = Auth::require();
    $upcoming = Models::upcomingBookings((int)$u['id']);
    $past     = Models::pastBookings((int)$u['id'], 20);
    View::render('app/dashboard', [
        'title' => 'Bookings', 'user' => $u, 'upcoming' => $upcoming, 'past' => $past,
        'counts' => Models::bookingCounts((int)$u['id']),
        'connected' => Models::calendarConnected((int)$u['id']),
        'eventTypes' => Models::eventTypes((int)$u['id']),
    ], 'app_layout');
}

function app_booking_cancel(): void
{
    $u = Auth::require();
    csrf_check();
    $b = Models::booking((int)($_POST['id'] ?? 0));
    if (!$b || (int)$b['user_id'] !== (int)$u['id']) {
        flash('err', 'Booking not found.');
        redirect('/app');
    }
    Booking::cancel($u, $b, 'host');
    flash('ok', 'Booking cancelled. ' . $b['guest_name'] . ' has been notified.');
    redirect('/app');
}

// ---------- event types ----------

function app_event_types(): void
{
    $u = Auth::require();
    $types = Models::eventTypes((int)$u['id']);
    $edit  = isset($_GET['edit']) ? Models::eventType((int)$u['id'], (int)$_GET['edit']) : null;
    View::render('app/event_types', [
        'title' => 'Event types', 'user' => $u, 'types' => $types, 'edit' => $edit,
        'limit' => plan_limit_event_types($u),
    ], 'app_layout');
}

function app_event_types_save(): void
{
    $u = Auth::require();
    csrf_check();
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $slug = slugify((string)($_POST['slug'] ?? '')) ?: slugify($name);
    $dur  = (int)($_POST['duration'] ?? 0);
    $desc = trim((string)($_POST['description'] ?? ''));
    $meet = !empty($_POST['meet']) ? 1 : 0;
    $active = !empty($_POST['active']) ? 1 : 0;

    $errs = [];
    if ($name === '' || mb_strlen($name) > 120) $errs[] = 'Name is required (max 120 chars).';
    if (!preg_match('/^[a-z0-9-]{1,60}$/', $slug)) $errs[] = 'URL slug must be letters, numbers and dashes.';
    if ($dur < 5 || $dur > 480) $errs[] = 'Duration must be 5–480 minutes.';
    if (mb_strlen($desc) > 500) $errs[] = 'Description is too long.';
    if (in_array($slug, ['app', 'api', 'm', 'login', 'logout', 'auth', 'stripe', 'privacy', 'terms', 'pricing', 'assets'], true)) $errs[] = 'That slug is reserved.';

    $dupe = DB::one('SELECT id FROM event_types WHERE user_id = ? AND slug = ? AND id <> ?', [$u['id'], $slug, $id]);
    if ($dupe) $errs[] = 'You already have an event type with that slug.';

    if (!$id) {
        $limit = plan_limit_event_types($u);
        if ($limit !== null && count(Models::eventTypes((int)$u['id'])) >= $limit) {
            $errs[] = "The free plan includes $limit event type" . ($limit === 1 ? '' : 's') . '. Upgrade to Pro for unlimited.';
        }
    }
    if ($errs) {
        foreach ($errs as $e) flash('err', $e);
        redirect('/app/event-types' . ($id ? "?edit=$id" : ''));
    }

    $data = ['name' => $name, 'slug' => $slug, 'duration' => $dur, 'description' => $desc, 'meet' => $meet, 'active' => $active];
    if ($id) {
        if (!Models::eventType((int)$u['id'], $id)) redirect('/app/event-types');
        DB::update('event_types', $data, 'id = :id AND user_id = :uid', ['id' => $id, 'uid' => $u['id']]);
        flash('ok', 'Event type updated.');
    } else {
        DB::insert('event_types', $data + ['user_id' => $u['id']]);
        flash('ok', 'Event type created. Share ' . app_url($u['username'] . '/' . $slug));
    }
    redirect('/app/event-types');
}

function app_event_types_delete(): void
{
    $u = Auth::require();
    csrf_check();
    DB::run('DELETE FROM event_types WHERE id = ? AND user_id = ?', [(int)($_POST['id'] ?? 0), $u['id']]);
    flash('ok', 'Event type deleted. Existing bookings are kept.');
    redirect('/app/event-types');
}

// ---------- availability ----------

function app_availability(): void
{
    $u = Auth::require();
    View::render('app/availability', ['title' => 'Availability', 'user' => $u], 'app_layout');
}

function app_availability_save(): void
{
    $u = Auth::require();
    csrf_check();
    $errs = [];
    $avail = [];
    foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d) {
        try {
            $avail[$d] = Availability::parseWindows((string)($_POST["avail_$d"] ?? ''));
        } catch (InvalidArgumentException $e) {
            $errs[] = ucfirst($d) . ': ' . $e->getMessage();
        }
    }
    $rules = [];
    foreach (['min_notice_hours' => [0, 720], 'max_days_ahead' => [1, 365], 'buffer_minutes' => [0, 240], 'slot_step_minutes' => [5, 240]] as $k => [$lo, $hi]) {
        $v = filter_var($_POST[$k] ?? null, FILTER_VALIDATE_INT);
        if ($v === false || $v < $lo || $v > $hi) $errs[] = str_replace('_', ' ', $k) . " must be $lo–$hi.";
        $rules[$k] = (int)$v;
    }
    $tz = (string)($_POST['timezone'] ?? '');
    if (!valid_tz($tz)) $errs[] = 'Unknown timezone.';

    if ($errs) {
        foreach ($errs as $e) flash('err', $e);
        redirect('/app/availability');
    }
    Models::updateUser((int)$u['id'], ['availability_json' => json_encode($avail), 'rules_json' => json_encode($rules), 'timezone' => $tz]);
    flash('ok', 'Availability saved.');
    redirect('/app/availability');
}

// ---------- settings ----------

function app_settings(): void
{
    $u = Auth::require();
    $token = Models::token((int)$u['id']);
    $calendars = [];
    if ($token && !$token['revoked']) {
        try {
            $calendars = Models::gcal($u)->listCalendars();
        } catch (Throwable $e) {
            log_error('listCalendars: ' . $e->getMessage());
            flash('err', 'Could not reach Google Calendar. Try reconnecting.');
        }
    }
    View::render('app/settings', ['title' => 'Settings', 'user' => $u, 'token' => $token, 'calendars' => $calendars], 'app_layout');
}

function app_settings_save(): void
{
    $u = Auth::require();
    csrf_check();
    $name = trim((string)($_POST['name'] ?? ''));
    $username = strtolower(trim((string)($_POST['username'] ?? '')));
    $welcome = trim((string)($_POST['welcome_text'] ?? ''));
    $errs = [];
    if ($name === '' || mb_strlen($name) > 120) $errs[] = 'Name is required.';
    if (!preg_match('/^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$/', $username)) $errs[] = 'Username: 3–40 chars, letters, numbers, dashes.';
    if (in_array($username, ['app', 'api', 'm', 'login', 'logout', 'auth', 'stripe', 'privacy', 'terms', 'pricing', 'assets', 'admin', 'www'], true)) $errs[] = 'That username is reserved.';
    if (Models::usernameTaken($username, (int)$u['id'])) $errs[] = 'That username is taken.';
    if (mb_strlen($welcome) > 500) $errs[] = 'Welcome text is too long.';

    $calId = (string)($_POST['calendar_id'] ?? 'primary');
    $busy  = array_values(array_filter((array)($_POST['busy'] ?? []), 'is_string'));
    if (!$busy) $busy = [$calId];
    if (!in_array($calId, $busy, true)) $busy[] = $calId;

    if ($errs) {
        foreach ($errs as $e) flash('err', $e);
        redirect('/app/settings');
    }
    Models::updateUser((int)$u['id'], ['name' => $name, 'username' => $username, 'welcome_text' => $welcome]);
    if (Models::token((int)$u['id'])) {
        DB::update('google_tokens', ['calendar_id' => $calId, 'busy_calendars_json' => json_encode($busy)], 'user_id = :user_id', ['user_id' => $u['id']]);
    }
    flash('ok', 'Settings saved.');
    redirect('/app/settings');
}

function app_calendar_disconnect(): void
{
    $u = Auth::require();
    csrf_check();
    $t = Models::token((int)$u['id']);
    if ($t) {
        try { GoogleCalendar::forApp()->revoke(Crypto::decrypt($t['refresh_token_enc'])); } catch (Throwable) {}
        DB::update('google_tokens', ['revoked' => 1, 'access_token' => null], 'user_id = :user_id', ['user_id' => $u['id']]);
    }
    flash('ok', 'Calendar disconnected. Your booking pages are paused until you reconnect.');
    redirect('/app/settings');
}

function app_account_delete(): void
{
    $u = Auth::require();
    csrf_check();
    if (($_POST['confirm'] ?? '') !== 'DELETE') {
        flash('err', 'Type DELETE to confirm.');
        redirect('/app/settings');
    }
    $t = Models::token((int)$u['id']);
    if ($t) {
        try { GoogleCalendar::forApp()->revoke(Crypto::decrypt($t['refresh_token_enc'])); } catch (Throwable) {}
    }
    DB::run('DELETE FROM users WHERE id = ?', [$u['id']]);   // cascades
    Auth::logout();
    session_start();
    flash('ok', 'Your account and data were deleted.');
    redirect('/');
}

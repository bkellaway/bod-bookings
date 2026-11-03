<?php
// Mock Google: OAuth token + userinfo + Calendar v3 subset. State in /tmp/mock_gcal.json
$store = '/tmp/mock_gcal.json';
$db = is_file($store) ? json_decode(file_get_contents($store), true) : ['events' => [], 'log' => []];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); $m = $_SERVER['REQUEST_METHOD'];
$raw = file_get_contents('php://input'); $body = json_decode($raw, true) ?? []; parse_str($raw, $form);
$db['log'][] = "$m $path " . json_encode($_GET);
header('Content-Type: application/json');
function out($d, $s = 200) { http_response_code($s); echo json_encode($d); }
if ($path === '/token') {
    if (($form['grant_type'] ?? '') === 'authorization_code') {
        // code encodes which fake user: "code-<sub>"
        $sub = substr($form['code'], 5);
        out(['access_token' => "at-$sub", 'refresh_token' => "rt-$sub", 'expires_in' => 3600, 'scope' => 'openid email profile https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly', 'token_type' => 'Bearer']);
    } else { out(['access_token' => 'at-refreshed', 'expires_in' => 3600]); }
} elseif ($path === '/userinfo') {
    $sub = substr($_SERVER['HTTP_AUTHORIZATION'] ?? '', 10); // "Bearer at-<sub>"
    out(['sub' => $sub, 'email' => "$sub@example.com", 'name' => ucfirst($sub) . ' Tester', 'email_verified' => true]);
} elseif ($path === '/revoke') { out([]);
} elseif ($path === '/users/me/calendarList') {
    out(['items' => [['id' => 'primary', 'summary' => 'Main', 'primary' => true], ['id' => 'team@group.calendar.google.com', 'summary' => 'Team']]]);
} elseif ($path === '/freeBusy') {
    $busy = [];
    foreach ($db['events'] as $e) if ($e['status'] !== 'cancelled') $busy[] = ['start' => $e['start']['dateTime'], 'end' => $e['end']['dateTime']];
    $busy[] = ['start' => '2026-09-30T17:00:00Z', 'end' => '2026-09-30T18:00:00Z']; // fixed existing meeting Wed 10-11 PT
    out(['calendars' => ['primary' => ['busy' => $busy]]]);
} elseif (preg_match('#^/calendars/[^/]+/events/([^/]+)$#', $path, $mm)) {
    $id = rawurldecode($mm[1]);
    if (!isset($db['events'][$id])) out(['error' => ['message' => 'Not Found']], 404);
    elseif ($m === 'GET') out($db['events'][$id]);
    elseif ($m === 'PATCH') { $db['events'][$id] = array_replace($db['events'][$id], $body); out($db['events'][$id]); }
    elseif ($m === 'DELETE') { $db['events'][$id]['status'] = 'cancelled'; http_response_code(204); }
} elseif (preg_match('#^/calendars/[^/]+/events$#', $path) && $m === 'POST') {
    $id = 'evt' . count($db['events']); $ev = $body + ['id' => $id, 'status' => 'confirmed'];
    if (isset($body['conferenceData'])) $ev['hangoutLink'] = "https://meet.google.com/mock-$id";
    $db['events'][$id] = $ev; out($ev);
} else out(['error' => ['message' => "unhandled $m $path"]], 500);
file_put_contents($store, json_encode($db, JSON_PRETTY_PRINT));

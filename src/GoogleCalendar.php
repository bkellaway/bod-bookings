<?php
declare(strict_types=1);

/**
 * Google OAuth + Calendar REST client, per user. Token persistence is delegated to
 * callbacks so the same class serves login (no token yet) and API calls (token from DB).
 */
final class GoogleCalendar
{
    public const SCOPES = 'openid email profile https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly';

    private string $authUrl  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private string $tokenUrl = 'https://oauth2.googleapis.com/token';
    private string $apiUrl   = 'https://www.googleapis.com/calendar/v3';
    private string $userinfoUrl = 'https://openidconnect.googleapis.com/v1/userinfo';
    private string $revokeUrl   = 'https://oauth2.googleapis.com/revoke';

    /** @var null|callable(): array{refresh_token:string,access_token:?string,expires_at:int} */
    private $load = null;
    /** @var null|callable(string $accessToken, int $expiresAt): void */
    private $save = null;

    public function __construct(private string $clientId, private string $clientSecret, private string $redirectUri)
    {
        // Test hooks only.
        if ($u = getenv('GCAL_TOKEN_URL'))    $this->tokenUrl = $u;
        if ($u = getenv('GCAL_API_URL'))      $this->apiUrl = $u;
        if ($u = getenv('GCAL_USERINFO_URL')) $this->userinfoUrl = $u;
    }

    public static function forApp(): self
    {
        return new self(Env::require('GOOGLE_CLIENT_ID'), Env::require('GOOGLE_CLIENT_SECRET'), rtrim(Env::require('APP_URL'), '/') . '/auth/callback');
    }

    public function withTokenStore(callable $load, callable $save): self
    {
        $c = clone $this;
        $c->load = $load;
        $c->save = $save;
        return $c;
    }

    // ---------- OAuth ----------

    public function authUrl(string $state, ?string $loginHint = null): string
    {
        $q = [
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->redirectUri,
            'response_type' => 'code',
            'scope'         => self::SCOPES,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'include_granted_scopes' => 'true',
            'state'         => $state,
        ];
        if ($loginHint) $q['login_hint'] = $loginHint;
        return $this->authUrl . '?' . http_build_query($q);
    }

    /** @return array{access_token:string,refresh_token:?string,expires_in:int,scope:string,id_token?:string} */
    public function exchangeCode(string $code): array
    {
        return $this->http('POST', $this->tokenUrl, [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->redirectUri,
            'grant_type'    => 'authorization_code',
        ], [], true);
    }

    /** @return array{sub:string,email:string,name?:string,picture?:string,email_verified?:bool} */
    public function userInfo(string $accessToken): array
    {
        return $this->http('GET', $this->userinfoUrl, null, ['Authorization: Bearer ' . $accessToken]);
    }

    public function revoke(string $refreshToken): void
    {
        try {
            $this->http('POST', $this->revokeUrl, ['token' => $refreshToken], [], true);
        } catch (Throwable) {
            // already revoked / invalid — fine
        }
    }

    private function accessToken(): string
    {
        if (!$this->load) {
            throw new LogicException('No token store bound');
        }
        $t = ($this->load)();
        if (empty($t['refresh_token'])) {
            throw new RuntimeException('Calendar not connected');
        }
        if (empty($t['access_token']) || (int)$t['expires_at'] < time() + 60) {
            $res = $this->http('POST', $this->tokenUrl, [
                'refresh_token' => $t['refresh_token'],
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type'    => 'refresh_token',
            ], [], true);
            $t['access_token'] = $res['access_token'];
            $t['expires_at']   = time() + (int)($res['expires_in'] ?? 3600);
            ($this->save)($t['access_token'], (int)$t['expires_at']);
        }
        return $t['access_token'];
    }

    // ---------- Calendar ----------

    public function listCalendars(): array
    {
        $res = $this->api('GET', '/users/me/calendarList', ['minAccessRole' => 'freeBusyReader']);
        return array_map(fn($c) => ['id' => $c['id'], 'summary' => $c['summary'] ?? $c['id'], 'primary' => !empty($c['primary'])], $res['items'] ?? []);
    }

    /** @return array<int, array{0:int,1:int}> */
    public function freeBusy(int $fromTs, int $toTs, array $calendarIds): array
    {
        $res = $this->api('POST', '/freeBusy', [], [
            'timeMin' => gmdate('c', $fromTs),
            'timeMax' => gmdate('c', $toTs),
            'items'   => array_map(fn($id) => ['id' => $id], $calendarIds),
        ]);
        $busy = [];
        foreach ($res['calendars'] ?? [] as $cal) {
            foreach ($cal['busy'] ?? [] as $b) {
                $busy[] = [strtotime($b['start']), strtotime($b['end'])];
            }
        }
        return $busy;
    }

    public function insertEvent(string $calendarId, array $event, bool $withMeet): array
    {
        if ($withMeet) {
            $event['conferenceData'] = ['createRequest' => [
                'requestId'             => bin2hex(random_bytes(8)),
                'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
            ]];
        }
        return $this->api('POST', '/calendars/' . rawurlencode($calendarId) . '/events',
            ['conferenceDataVersion' => 1, 'sendUpdates' => 'all'], $event);
    }

    public function patchEvent(string $calendarId, string $eventId, array $patch): array
    {
        return $this->api('PATCH', '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId), ['sendUpdates' => 'all'], $patch);
    }

    public function deleteEvent(string $calendarId, string $eventId): void
    {
        try {
            $this->api('DELETE', '/calendars/' . rawurlencode($calendarId) . '/events/' . rawurlencode($eventId), ['sendUpdates' => 'all']);
        } catch (GoogleApiException $e) {
            if (!in_array($e->getCode(), [404, 410], true)) throw $e;
        }
    }

    public function api(string $method, string $path, array $query = [], ?array $body = null): array
    {
        $url = $this->apiUrl . $path . ($query ? '?' . http_build_query($query) : '');
        return $this->http($method, $url, $body, ['Authorization: Bearer ' . $this->accessToken()]);
    }

    // ---------- HTTP ----------

    private function http(string $method, string $url, ?array $body, array $headers, bool $form = false): array
    {
        $ch   = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS]   = $form ? http_build_query($body) : json_encode($body);
            $opts[CURLOPT_HTTPHEADER][] = $form ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json';
        }
        curl_setopt_array($ch, $opts);
        $raw    = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new GoogleApiException("Google request failed: $err", 0);
        }
        $data = $raw === '' ? [] : (json_decode($raw, true) ?? []);
        if ($status < 200 || $status >= 300) {
            $msg = $data['error']['message'] ?? $data['error_description'] ?? $data['error'] ?? $raw;
            throw new GoogleApiException('Google API error (' . $status . '): ' . (is_array($msg) ? json_encode($msg) : $msg), $status);
        }
        return $data;
    }
}

final class GoogleApiException extends RuntimeException {}

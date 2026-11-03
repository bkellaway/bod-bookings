<?php
declare(strict_types=1);

/** Thin data-access layer. All SQL lives here. */
final class Models
{
    // ---------- users ----------

    public static function user(int $id): ?array
    {
        return self::hydrateUser(DB::one('SELECT * FROM users WHERE id = ?', [$id]));
    }

    public static function userByUsername(string $username): ?array
    {
        return self::hydrateUser(DB::one('SELECT * FROM users WHERE username = ?', [strtolower($username)]));
    }

    public static function userByGoogleSub(string $sub): ?array
    {
        return self::hydrateUser(DB::one('SELECT * FROM users WHERE google_sub = ?', [$sub]));
    }

    public static function userByEmail(string $email): ?array
    {
        return self::hydrateUser(DB::one('SELECT * FROM users WHERE email = ?', [strtolower($email)]));
    }

    public static function userByStripeCustomer(string $cus): ?array
    {
        return self::hydrateUser(DB::one('SELECT * FROM users WHERE stripe_customer_id = ?', [$cus]));
    }

    private static function hydrateUser(?array $u): ?array
    {
        if (!$u) return null;
        $u['availability'] = json_decode($u['availability_json'], true) ?: Availability::DEFAULT_AVAILABILITY;
        $u['rules']        = (json_decode($u['rules_json'], true) ?: []) + Availability::DEFAULT_RULES;
        return $u;
    }

    public static function createUser(string $email, string $name, string $sub, string $timezone): int
    {
        $base = slugify(explode('@', $email)[0]) ?: 'user';
        $base = substr($base, 0, 30);
        $username = $base;
        for ($i = 2; DB::one('SELECT 1 FROM users WHERE username = ?', [$username]); $i++) {
            $username = $base . $i;
        }
        return DB::insert('users', [
            'email'             => strtolower($email),
            'name'              => $name !== '' ? $name : explode('@', $email)[0],
            'username'          => $username,
            'google_sub'        => $sub,
            'timezone'          => $timezone,
            'availability_json' => json_encode(Availability::DEFAULT_AVAILABILITY),
            'rules_json'        => json_encode(Availability::DEFAULT_RULES),
        ]);
    }

    public static function updateUser(int $id, array $data): void
    {
        DB::update('users', $data, 'id = :id', ['id' => $id]);
    }

    public static function usernameTaken(string $username, int $exceptUserId): bool
    {
        return (bool)DB::one('SELECT 1 FROM users WHERE username = ? AND id <> ?', [$username, $exceptUserId]);
    }

    // ---------- google tokens ----------

    public static function token(int $userId): ?array
    {
        $t = DB::one('SELECT * FROM google_tokens WHERE user_id = ?', [$userId]);
        if (!$t) return null;
        $t['busy_calendars'] = json_decode($t['busy_calendars_json'], true) ?: ['primary'];
        return $t;
    }

    public static function saveToken(int $userId, string $refreshToken, string $accessToken, int $expiresAt, string $scope): void
    {
        $data = [
            'refresh_token_enc' => Crypto::encrypt($refreshToken),
            'access_token'      => $accessToken,
            'expires_at'        => $expiresAt,
            'scope'             => $scope,
            'revoked'           => 0,
        ];
        if (DB::one('SELECT 1 FROM google_tokens WHERE user_id = ?', [$userId])) {
            DB::update('google_tokens', $data, 'user_id = :user_id', ['user_id' => $userId]);
        } else {
            DB::insert('google_tokens', $data + ['user_id' => $userId, 'busy_calendars_json' => json_encode(['primary'])]);
        }
    }

    public static function gcal(array $user): GoogleCalendar
    {
        $uid = (int)$user['id'];
        return GoogleCalendar::forApp()->withTokenStore(
            function () use ($uid) {
                $t = Models::token($uid);
                if (!$t || $t['revoked']) return ['refresh_token' => '', 'access_token' => null, 'expires_at' => 0];
                return ['refresh_token' => Crypto::decrypt($t['refresh_token_enc']), 'access_token' => $t['access_token'], 'expires_at' => (int)$t['expires_at']];
            },
            fn(string $at, int $exp) => DB::update('google_tokens', ['access_token' => $at, 'expires_at' => $exp], 'user_id = :user_id', ['user_id' => $uid]),
        );
    }

    public static function calendarConnected(int $userId): bool
    {
        $t = self::token($userId);
        return $t !== null && !$t['revoked'];
    }

    // ---------- event types ----------

    public static function eventTypes(int $userId, bool $activeOnly = false): array
    {
        return DB::all('SELECT * FROM event_types WHERE user_id = ?' . ($activeOnly ? ' AND active = 1' : '') . ' ORDER BY sort_order, id', [$userId]);
    }

    public static function eventType(int $userId, int $id): ?array
    {
        return DB::one('SELECT * FROM event_types WHERE user_id = ? AND id = ?', [$userId, $id]);
    }

    public static function eventTypeBySlug(int $userId, string $slug): ?array
    {
        return DB::one('SELECT * FROM event_types WHERE user_id = ? AND slug = ? AND active = 1', [$userId, $slug]);
    }

    // ---------- bookings ----------

    public static function booking(int $id): ?array
    {
        return DB::one('SELECT * FROM bookings WHERE id = ?', [$id]);
    }

    public static function bookingByToken(string $token): ?array
    {
        return DB::one('SELECT * FROM bookings WHERE manage_token = ?', [$token]);
    }

    public static function upcomingBookings(int $userId, int $limit = 100): array
    {
        return DB::all("SELECT * FROM bookings WHERE user_id = ? AND status = 'confirmed' AND end_utc >= ? ORDER BY start_utc LIMIT $limit",
            [$userId, gmdate('Y-m-d H:i:s')]);
    }

    public static function pastBookings(int $userId, int $limit = 50): array
    {
        return DB::all("SELECT * FROM bookings WHERE user_id = ? AND (status <> 'confirmed' OR end_utc < ?) ORDER BY start_utc DESC LIMIT $limit",
            [$userId, gmdate('Y-m-d H:i:s')]);
    }

    public static function bookingCounts(int $userId): array
    {
        $r = DB::one("SELECT
            SUM(CASE WHEN status='confirmed' AND start_utc >= ? THEN 1 ELSE 0 END) AS upcoming,
            SUM(CASE WHEN status='confirmed' THEN 1 ELSE 0 END) AS total_confirmed,
            SUM(CASE WHEN status='cancelled' THEN 1 ELSE 0 END) AS cancelled
            FROM bookings WHERE user_id = ?", [gmdate('Y-m-d H:i:s'), $userId]);
        return array_map('intval', $r ?: ['upcoming' => 0, 'total_confirmed' => 0, 'cancelled' => 0]);
    }
}

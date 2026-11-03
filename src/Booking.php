<?php
declare(strict_types=1);

/** Booking lifecycle: create / reschedule / cancel, keeping Google Calendar and the DB in sync. */
final class Booking
{
    public static function availability(array $user, ?int $now = null): Availability
    {
        return new Availability($user['availability'], $user['rules'], $user['timezone'], $now);
    }

    /** @return array<int, array{0:int,1:int}> */
    public static function busy(array $user, int $from, int $to): array
    {
        $t = Models::token((int)$user['id']);
        if (!$t || $t['revoked']) {
            throw new RuntimeException('Calendar not connected');
        }
        return Models::gcal($user)->freeBusy($from, $to, $t['busy_calendars']);
    }

    public static function create(array $user, array $type, int $start, string $name, string $email, string $notes, string $guestTz): array
    {
        $end  = $start + (int)$type['duration'] * 60;
        $busy = self::busy($user, $start - 86400, $end + 86400);
        if (!self::availability($user)->isBookable((int)$type['duration'], $start, $busy)) {
            throw new SlotUnavailable();
        }
        $token  = Crypto::token(32);
        $manage = app_url('m/' . $token);
        $t      = Models::token((int)$user['id']);

        $event = [
            'summary'     => "{$type['name']}: {$name} and {$user['name']}",
            'description' => ($notes !== '' ? "Notes from {$name}:\n{$notes}\n\n" : '') . "Booked via " . Env::get('APP_NAME') . ".\nReschedule or cancel: $manage",
            'start'       => ['dateTime' => gmdate('c', $start), 'timeZone' => $user['timezone']],
            'end'         => ['dateTime' => gmdate('c', $end),   'timeZone' => $user['timezone']],
            'attendees'   => [['email' => $email, 'displayName' => $name]],
            'guestsCanInviteOthers' => false,
            'reminders'   => ['useDefault' => true],
            'extendedProperties' => ['private' => ['bod_booking' => '1']],
        ];
        $created = Models::gcal($user)->insertEvent($t['calendar_id'], $event, (bool)$type['meet']);

        $id = DB::insert('bookings', [
            'user_id'         => $user['id'],
            'event_type_id'   => $type['id'],
            'event_type_name' => $type['name'],
            'duration'        => $type['duration'],
            'google_event_id' => $created['id'] ?? null,
            'meet_link'       => $created['hangoutLink'] ?? null,
            'guest_name'      => $name,
            'guest_email'     => $email,
            'guest_tz'        => $guestTz,
            'notes'           => $notes,
            'start_utc'       => gmdate('Y-m-d H:i:s', $start),
            'end_utc'         => gmdate('Y-m-d H:i:s', $end),
            'status'          => 'confirmed',
            'manage_token'    => $token,
        ]);
        $b = Models::booking($id);
        self::notify('confirmed', $user, $b);
        return $b;
    }

    public static function reschedule(array $user, array $b, int $start): array
    {
        $end  = $start + (int)$b['duration'] * 60;
        $curS = strtotime($b['start_utc'] . ' UTC');
        $curE = strtotime($b['end_utc'] . ' UTC');
        $busy = array_values(array_filter(self::busy($user, $start - 86400, $end + 86400), fn($x) => !($x[0] === $curS && $x[1] === $curE)));
        if (!self::availability($user)->isBookable((int)$b['duration'], $start, $busy)) {
            throw new SlotUnavailable();
        }
        $t = Models::token((int)$user['id']);
        if ($b['google_event_id']) {
            Models::gcal($user)->patchEvent($t['calendar_id'], $b['google_event_id'], [
                'start' => ['dateTime' => gmdate('c', $start), 'timeZone' => $user['timezone']],
                'end'   => ['dateTime' => gmdate('c', $end),   'timeZone' => $user['timezone']],
            ]);
        }
        DB::update('bookings', ['start_utc' => gmdate('Y-m-d H:i:s', $start), 'end_utc' => gmdate('Y-m-d H:i:s', $end)], 'id = :id', ['id' => $b['id']]);
        $b = Models::booking((int)$b['id']);
        self::notify('rescheduled', $user, $b);
        return $b;
    }

    public static function cancel(array $user, array $b, string $by): void
    {
        if ($b['status'] === 'cancelled') return;
        $t = Models::token((int)$user['id']);
        if ($b['google_event_id'] && $t && !$t['revoked']) {
            try {
                Models::gcal($user)->deleteEvent($t['calendar_id'], $b['google_event_id']);
            } catch (Throwable $e) {
                log_error('cancel: ' . $e->getMessage());
            }
        }
        DB::update('bookings', ['status' => 'cancelled'], 'id = :id', ['id' => $b['id']]);
        self::notify('cancelled', $user, Models::booking((int)$b['id']), $by);
    }

    public static function view(array $b, array $user): array
    {
        return [
            'id'        => (int)$b['id'],
            'type_name' => $b['event_type_name'],
            'duration'  => (int)$b['duration'],
            'start'     => gmdate('c', strtotime($b['start_utc'] . ' UTC')),
            'end'       => gmdate('c', strtotime($b['end_utc'] . ' UTC')),
            'guest'     => $b['guest_email'],
            'name'      => $b['guest_name'],
            'tz'        => $b['guest_tz'],
            'meet_link' => $b['meet_link'],
            'status'    => $b['status'],
            'host'      => $user['name'],
            'username'  => $user['username'],
            'manage_url' => app_url('m/' . $b['manage_token']),
        ];
    }

    private static function notify(string $kind, array $user, array $b, string $by = 'guest'): void
    {
        $start = strtotime($b['start_utc'] . ' UTC');
        $label = ['confirmed' => 'Confirmed', 'rescheduled' => 'Rescheduled', 'cancelled' => 'Cancelled'][$kind];
        $manage = app_url('m/' . $b['manage_token']);
        $lines = ["{$b['event_type_name']} with {$user['name']}", 'When: ' . fmt_when($start, $b['guest_tz']), "Duration: {$b['duration']} minutes"];
        if ($b['meet_link'] && $kind !== 'cancelled') $lines[] = 'Google Meet: ' . $b['meet_link'];
        if ($kind !== 'cancelled') $lines[] = "Reschedule or cancel: $manage";
        $app = Env::get('APP_NAME', 'Bookings');

        Mailer::send($b['guest_email'], "$label: {$b['event_type_name']} with {$user['name']}",
            "Hi {$b['guest_name']},\n\nYour booking is $label" . ($kind === 'cancelled' && $by === 'host' ? " by {$user['name']}" : '') . ".\n\n" . implode("\n", $lines) . "\n\n— $app", $user['email']);
        Mailer::send($user['email'], "[$label] {$b['event_type_name']} — {$b['guest_name']}",
            "$label booking by {$b['guest_name']} <{$b['guest_email']}>.\n\n" . implode("\n", $lines) . "\nHost time: " . fmt_when($start, $user['timezone'])
            . ($b['notes'] ? "\n\nNotes:\n{$b['notes']}" : '') . "\n\nManage: " . app_url('app'), $b['guest_email']);
    }
}

final class SlotUnavailable extends RuntimeException
{
    public function __construct() { parent::__construct('That time is no longer available. Please choose another.'); }
}

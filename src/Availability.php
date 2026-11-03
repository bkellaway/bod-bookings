<?php
declare(strict_types=1);

/**
 * Weekly hours + calendar busy blocks -> bookable slot start timestamps.
 * $availability: ['mon' => [['09:00','17:00'], ...], ...]   (host timezone)
 * $rules: min_notice_hours, max_days_ahead, buffer_minutes, slot_step_minutes
 */
final class Availability
{
    public const DEFAULT_AVAILABILITY = [
        'mon' => [['09:00', '17:00']], 'tue' => [['09:00', '17:00']], 'wed' => [['09:00', '17:00']],
        'thu' => [['09:00', '17:00']], 'fri' => [['09:00', '17:00']], 'sat' => [], 'sun' => [],
    ];
    public const DEFAULT_RULES = ['min_notice_hours' => 4, 'max_days_ahead' => 30, 'buffer_minutes' => 10, 'slot_step_minutes' => 30];

    private DateTimeZone $tz;
    private int $now;

    public function __construct(private array $availability, private array $rules, string $timezone, ?int $now = null)
    {
        $this->tz    = new DateTimeZone($timezone);
        $this->now   = $now ?? time();
        $this->rules = $rules + self::DEFAULT_RULES;
    }

    public function window(): array
    {
        $from = $this->now + (int)$this->rules['min_notice_hours'] * 3600;
        $to   = (new DateTimeImmutable('@' . $this->now))->setTimezone($this->tz)
            ->modify('+' . (int)$this->rules['max_days_ahead'] . ' days')->setTime(23, 59, 59)->getTimestamp();
        return [$from, $to];
    }

    /** @return int[] */
    public function slots(int $durationMin, int $fromTs, int $toTs, array $busy): array
    {
        [$wf, $wt] = $this->window();
        $fromTs = max($fromTs, $wf);
        $toTs   = min($toTs, $wt);
        if ($fromTs > $toTs) return [];

        $duration = $durationMin * 60;
        $step     = (int)$this->rules['slot_step_minutes'] * 60;
        $buffer   = (int)$this->rules['buffer_minutes'] * 60;

        $day  = (new DateTimeImmutable('@' . $fromTs))->setTimezone($this->tz)->setTime(0, 0);
        $last = (new DateTimeImmutable('@' . $toTs))->setTimezone($this->tz)->setTime(0, 0);

        $out = [];
        for (; $day <= $last; $day = $day->modify('+1 day')) {
            $dow = strtolower($day->format('D'));
            foreach ($this->availability[$dow] ?? [] as [$open, $close]) {
                [$oh, $om] = array_map('intval', explode(':', $open));
                [$ch, $cm] = array_map('intval', explode(':', $close));
                $start = $day->setTime($oh, $om)->getTimestamp();
                $end   = $day->setTime($ch, $cm)->getTimestamp();
                for ($s = $start; $s + $duration <= $end; $s += $step) {
                    if ($s < $fromTs || $s > $toTs) continue;
                    if (!$this->conflicts($s, $s + $duration, $busy, $buffer)) $out[] = $s;
                }
            }
        }
        sort($out);
        return array_values(array_unique($out));
    }

    public function isBookable(int $durationMin, int $startTs, array $busy): bool
    {
        return in_array($startTs, $this->slots($durationMin, $startTs, $startTs, $busy), true);
    }

    private function conflicts(int $s, int $e, array $busy, int $buffer): bool
    {
        foreach ($busy as [$bs, $be]) {
            if ($s < $be + $buffer && $e + $buffer > $bs) return true;
        }
        return false;
    }

    /** Parse "09:00-12:00, 13:00-17:00" -> [['09:00','12:00'],['13:00','17:00']] or throw. */
    public static function parseWindows(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') return [];
        $out = [];
        foreach (preg_split('/\s*,\s*/', $raw) as $w) {
            if (!preg_match('/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', $w, $m)) {
                throw new InvalidArgumentException("Can't read \"$w\" — use 09:00-17:00");
            }
            $a = (int)$m[1] * 60 + (int)$m[2];
            $b = (int)$m[3] * 60 + (int)$m[4];
            if ((int)$m[2] > 59 || (int)$m[4] > 59 || $a >= $b || $b > 1440) {
                throw new InvalidArgumentException("Window \"$w\" is invalid");
            }
            $out[] = [sprintf('%02d:%02d', intdiv($a, 60), $a % 60), sprintf('%02d:%02d', intdiv($b, 60), $b % 60)];
        }
        return $out;
    }
}

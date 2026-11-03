<?php
declare(strict_types=1);
require __DIR__ . '/../src/Availability.php';
$tz = new DateTimeZone('America/Los_Angeles');
$avail = ['mon' => [['09:00','17:00']], 'tue' => [['09:00','17:00']], 'wed' => [['09:00','17:00']], 'thu' => [['09:00','17:00']], 'fri' => [['09:00','15:00']], 'sat' => [], 'sun' => []];
$rules = ['min_notice_hours' => 4, 'max_days_ahead' => 30, 'buffer_minutes' => 15, 'slot_step_minutes' => 30];
$ts  = fn(string $s) => (new DateTimeImmutable($s, $tz))->getTimestamp();
$fmt = fn(int $t) => (new DateTimeImmutable('@' . $t))->setTimezone($tz)->format('D H:i');
$fail = 0;
function check(string $l, bool $ok): void { global $fail; echo ($ok ? 'PASS' : 'FAIL') . "  $l\n"; if (!$ok) $fail++; }

$busy = [[$ts('2026-09-30 10:00'), $ts('2026-09-30 11:00')]];
$av = new Availability($avail, $rules, 'America/Los_Angeles', $ts('2026-09-30 08:00'));
$s = array_map($fmt, $av->slots(30, $ts('2026-09-30 00:00'), $ts('2026-09-30 23:59'), $busy));
check('min notice: first slot 12:00', ($s[0] ?? '') === 'Wed 12:00');
check('30-min: last slot 16:30', end($s) === 'Wed 16:30');

$av2 = new Availability($avail, $rules, 'America/Los_Angeles', $ts('2026-09-29 08:00'));
$s2 = array_map($fmt, $av2->slots(30, $ts('2026-09-30 00:00'), $ts('2026-09-30 23:59'), $busy));
check('09:30 blocked by buffer before 10:00 meeting', !in_array('Wed 09:30', $s2, true));
check('09:00 ok', in_array('Wed 09:00', $s2, true));
check('11:00 blocked by buffer after meeting', !in_array('Wed 11:00', $s2, true));
check('11:30 ok', in_array('Wed 11:30', $s2, true));
$s3 = array_map($fmt, $av2->slots(60, $ts('2026-09-30 00:00'), $ts('2026-09-30 23:59'), []));
check('60-min: last slot 16:00', end($s3) === 'Wed 16:00');
check('Saturday empty', $av2->slots(15, $ts('2026-10-03 00:00'), $ts('2026-10-03 23:59'), []) === []);
$fri = array_map($fmt, $av2->slots(15, $ts('2026-10-02 00:00'), $ts('2026-10-02 23:59'), []));
check('Friday last 15-min slot 14:30', end($fri) === 'Fri 14:30');
check('beyond horizon empty', $av2->slots(15, $ts('2026-10-31 00:00'), $ts('2026-10-31 23:59'), []) === []);
check('isBookable valid', $av2->isBookable(30, $ts('2026-09-30 13:00'), $busy));
check('isBookable off-grid false', !$av2->isBookable(30, $ts('2026-09-30 13:10'), $busy));
check('isBookable busy false', !$av2->isBookable(30, $ts('2026-09-30 10:00'), $busy));
$av3 = new Availability($avail, $rules, 'America/Los_Angeles', $ts('2026-10-20 08:00'));
$mon = $av3->slots(15, $ts('2026-11-02 00:00'), $ts('2026-11-02 23:59'), []);
check('after DST fall-back first slot 09:00 local = 17:00Z', $fmt($mon[0]) === 'Mon 09:00' && gmdate('H:i', $mon[0]) === '17:00');
$split = new Availability(['thu' => [['09:00','12:00'],['13:00','17:00']]] + $avail, $rules, 'America/Los_Angeles', $ts('2026-09-29 08:00'));
$th = array_map($fmt, $split->slots(30, $ts('2026-10-01 00:00'), $ts('2026-10-01 23:59'), []));
check('split day: 11:30 ok, 12:00 not offered (lunch), 13:00 ok', in_array('Thu 11:30', $th, true) && !in_array('Thu 12:00', $th, true) && in_array('Thu 13:00', $th, true));
check('parseWindows normalises', Availability::parseWindows('9:00-12:00, 13:00 - 17:00') === [['09:00','12:00'],['13:00','17:00']]);
try { Availability::parseWindows('17:00-09:00'); check('parseWindows rejects inverted', false); } catch (InvalidArgumentException) { check('parseWindows rejects inverted', true); }
echo $fail ? "\n$fail FAILED\n" : "\nALL PASS\n"; exit($fail ? 1 : 0);

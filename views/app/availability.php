<?php /** @var array $user */ $a = $user['availability']; $r = $user['rules'];
$str = fn(string $d) => implode(', ', array_map(fn($w) => "$w[0]-$w[1]", $a[$d] ?? [])); ?>
<h1>Availability</h1>
<p class="muted">Guests can only book inside these hours, and never over anything already on your calendar.</p>

<form method="post" action="/app/availability" class="form card">
  <?= csrf_field() ?>
  <fieldset><legend>Weekly hours — e.g. <code>09:00-12:00, 13:00-17:00</code>, blank = unavailable</legend>
    <div class="grid2">
    <?php foreach (['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'] as $d => $label): ?>
      <label><?= $label ?> <input name="avail_<?= $d ?>" value="<?= h($str($d)) ?>" placeholder="09:00-17:00"></label>
    <?php endforeach; ?>
    </div>
    <label>Timezone
      <select name="timezone"><?php foreach (DateTimeZone::listIdentifiers() as $z): ?><option <?= $user['timezone'] === $z ? 'selected' : '' ?>><?= h($z) ?></option><?php endforeach; ?></select>
    </label>
  </fieldset>
  <fieldset><legend>Rules</legend>
    <div class="grid2">
      <label>Minimum notice (hours) <input type="number" name="min_notice_hours" min="0" max="720" value="<?= (int)$r['min_notice_hours'] ?>"></label>
      <label>How far ahead people can book (days) <input type="number" name="max_days_ahead" min="1" max="365" value="<?= (int)$r['max_days_ahead'] ?>"></label>
      <label>Buffer around existing events (minutes) <input type="number" name="buffer_minutes" min="0" max="240" value="<?= (int)$r['buffer_minutes'] ?>"></label>
      <label>Start times every (minutes) <input type="number" name="slot_step_minutes" min="5" max="240" value="<?= (int)$r['slot_step_minutes'] ?>"></label>
    </div>
  </fieldset>
  <div><button class="btn">Save availability</button></div>
</form>

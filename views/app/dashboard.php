<?php
/** @var array $user, $upcoming, $past, $counts, $eventTypes */
$tz  = new DateTimeZone($user['timezone']);
$fmt = fn(string $utc) => (new DateTimeImmutable($utc . ' UTC'))->setTimezone($tz)->format('D M j · g:i A');
?>
<h1>Bookings</h1>

<?php if (!$connected): ?>
  <p class="notice err">Your Google Calendar is not connected. <a href="/auth/google">Reconnect</a> to accept bookings.</p>
<?php elseif (!$eventTypes): ?>
  <p class="notice ok">You're connected. <a href="/app/event-types">Create your first event type</a> to get a booking link.</p>
<?php endif; ?>

<div class="stats">
  <div class="stat"><span class="num"><?= $counts['upcoming'] ?></span><span class="lbl">Upcoming</span></div>
  <div class="stat"><span class="num"><?= $counts['total_confirmed'] ?></span><span class="lbl">Total booked</span></div>
  <div class="stat"><span class="num"><?= $counts['cancelled'] ?></span><span class="lbl">Cancelled</span></div>
  <div class="stat link"><a href="/<?= h($user['username']) ?>" target="_blank">Your page ↗</a><span class="lbl muted"><?= h(app_url($user['username'])) ?></span></div>
</div>

<h2>Upcoming <span class="muted small">times in <?= h($user['timezone']) ?></span></h2>
<?php if (!$upcoming): ?><p class="card muted">Nothing booked yet.</p><?php else: ?>
<table>
  <tr><th>When</th><th>Event</th><th>Guest</th><th>Meet</th><th></th></tr>
  <?php foreach ($upcoming as $b): ?>
  <tr>
    <td><?= h($fmt($b['start_utc'])) ?></td>
    <td><?= h($b['event_type_name']) ?> <span class="muted">(<?= (int)$b['duration'] ?>m)</span></td>
    <td><?= h($b['guest_name']) ?><br><a href="mailto:<?= h($b['guest_email']) ?>" class="small"><?= h($b['guest_email']) ?></a>
      <?php if ($b['notes']): ?><details class="small"><summary>Notes</summary><?= nl2br(h($b['notes'])) ?></details><?php endif; ?></td>
    <td><?php if ($b['meet_link']): ?><a href="<?= h($b['meet_link']) ?>" target="_blank">Join</a><?php endif; ?></td>
    <td class="nowrap">
      <a class="btn secondary mini" href="/m/<?= h($b['manage_token']) ?>" target="_blank">Reschedule</a>
      <form method="post" action="/app/bookings/cancel" class="inline" onsubmit="return confirm('Cancel this booking and notify the guest?')">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn danger mini">Cancel</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($past): ?>
<h2 class="mt">Past &amp; cancelled</h2>
<table class="muted">
  <tr><th>When</th><th>Event</th><th>Guest</th><th>Status</th></tr>
  <?php foreach ($past as $b): ?>
  <tr><td><?= h($fmt($b['start_utc'])) ?></td><td><?= h($b['event_type_name']) ?></td><td><?= h($b['guest_name']) ?></td><td><?= h($b['status']) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

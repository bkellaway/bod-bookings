<?php /** @var array $user, $calendars; ?array $token */ $connected = $token && !$token['revoked']; ?>
<h1>Settings</h1>

<form method="post" action="/app/settings" class="form card">
  <?= csrf_field() ?>
  <fieldset><legend>Profile</legend>
    <div class="grid2">
      <label>Your name <input name="name" required maxlength="120" value="<?= h($user['name']) ?>"></label>
      <label>Username <span class="muted small">(<?= h(app_url('')) ?>/…)</span> <input name="username" required pattern="[a-z0-9-]{3,40}" value="<?= h($user['username']) ?>"></label>
    </div>
    <label>Welcome text on your page <textarea name="welcome_text" rows="2" maxlength="500"><?= h($user['welcome_text']) ?></textarea></label>
    <p class="muted small">Signed in as <?= h($user['email']) ?>. Changing your username changes all your booking links.</p>
  </fieldset>

  <fieldset><legend>Google Calendar <span class="pill <?= $connected ? '' : 'off' ?>"><?= $connected ? 'connected' : 'not connected' ?></span></legend>
    <?php if ($connected && $calendars): ?>
      <label>Bookings are added to
        <select name="calendar_id"><?php foreach ($calendars as $c): ?>
          <option value="<?= h($c['id']) ?>" <?= $token['calendar_id'] === $c['id'] || ($token['calendar_id'] === 'primary' && $c['primary']) ? 'selected' : '' ?>><?= h($c['summary']) ?></option>
        <?php endforeach; ?></select></label>
      <div><span class="small muted">Check these calendars for conflicts:</span>
        <?php foreach ($calendars as $c): $checked = in_array($c['id'], $token['busy_calendars'], true) || (in_array('primary', $token['busy_calendars'], true) && $c['primary']); ?>
          <label class="chk"><input type="checkbox" name="busy[]" value="<?= h($c['id']) ?>" <?= $checked ? 'checked' : '' ?>> <?= h($c['summary']) ?></label>
        <?php endforeach; ?>
      </div>
    <?php elseif ($connected): ?>
      <p class="muted">Couldn't list your calendars right now. Bookings go to <code><?= h($token['calendar_id']) ?></code>.</p>
    <?php else: ?>
      <p class="muted">Connect your calendar to accept bookings.</p>
    <?php endif; ?>
    <div class="row">
      <a class="btn secondary mini" href="/auth/google"><?= $connected ? 'Reconnect' : 'Connect Google Calendar' ?></a>
    </div>
  </fieldset>
  <div><button class="btn">Save settings</button></div>
</form>

<?php if ($connected): ?>
<form method="post" action="/app/calendar/disconnect" class="card mt" onsubmit="return confirm('Disconnect Google Calendar? Your booking pages will stop working until you reconnect.')">
  <?= csrf_field() ?>
  <p class="muted small">Disconnecting revokes our access to your Google account and pauses your booking pages.</p>
  <button class="btn secondary mini">Disconnect calendar</button>
</form>
<?php endif; ?>

<details class="card mt danger-zone">
  <summary>Delete account</summary>
  <form method="post" action="/app/account/delete" class="form">
    <?= csrf_field() ?>
    <p class="muted small">Deletes your profile, event types, booking records and calendar token. Events already on your Google Calendar are not touched. Type <strong>DELETE</strong> to confirm.</p>
    <label><input name="confirm" placeholder="DELETE"></label>
    <div><button class="btn danger">Delete my account</button></div>
  </form>
</details>

<?php $app = Env::get('APP_NAME', 'Bookings'); ?>
<section class="hero">
  <h1>Let people book time with you — straight onto your Google Calendar.</h1>
  <p>Share one link. Guests pick a free slot in their own timezone, get a calendar invite and a Google Meet link, and can reschedule themselves. No back-and-forth.</p>
  <a class="btn" href="/login">Get started free</a>
  <p class="muted small">Free plan includes <?= Env::int('FREE_EVENT_TYPES', 1) ?> event type and unlimited bookings.</p>
</section>

<div class="features">
  <div class="card"><h3>Reads your real availability</h3><p class="muted">Weekly hours minus everything already on your calendar, with buffers and minimum notice. Never double-booked.</p></div>
  <div class="card"><h3>Works where guests are</h3><p class="muted">Times show in the guest's timezone. Invites, Meet links and reschedule links are handled automatically.</p></div>
  <div class="card"><h3>Your data stays yours</h3><p class="muted">We ask only for calendar access, store tokens encrypted, and you can disconnect or delete your account any time.</p></div>
</div>

<section class="card narrow" style="margin:0 auto">
  <h3>How it works</h3>
  <ol>
    <li>Sign in with Google and allow calendar access.</li>
    <li>Set your weekly hours and create an event type (e.g. "30-minute consult").</li>
    <li>Share <code><?= h(app_url('you/30-min')) ?></code>.</li>
  </ol>
</section>

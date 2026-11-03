<?php $app = Env::get('APP_NAME'); $contact = Env::get('MAIL_FROM'); ?>
<article class="prose">
<h1>Privacy Policy</h1>
<p class="muted">Last updated: <?= date('F j, Y') ?></p>
<p><?= h($app) ?> ("the Service") is operated by Back Office Designs, Carlsbad, California. This policy explains what we collect and how we use it.</p>

<h2>Information we collect</h2>
<ul>
  <li><strong>Account holders:</strong> your name, email address and Google account identifier, obtained when you sign in with Google.</li>
  <li><strong>Google Calendar data:</strong> with your permission we read free/busy information from the calendars you select and create, update or delete the calendar events that correspond to bookings made through the Service. We do not read the content of your other events.</li>
  <li><strong>Guests who book:</strong> name, email address, timezone, the time booked and any notes entered on the booking form.</li>
  <li><strong>Billing:</strong> payments are processed by Stripe. We store your Stripe customer and subscription identifiers, not card numbers.</li>
  <li><strong>Technical:</strong> standard server logs (IP address, browser, pages requested) retained for a limited time for security and debugging.</li>
</ul>

<h2>How we use Google user data</h2>
<p>Our use of information received from Google APIs adheres to the <a href="https://developers.google.com/terms/api-services-user-data-policy" rel="noopener">Google API Services User Data Policy</a>, including the Limited Use requirements. Calendar data is used only to display your availability and to create, update and cancel booking events on your behalf. We do not sell Google user data, use it for advertising, or allow humans to read it except with your consent, for security purposes, or as required by law.</p>

<h2>Storage and security</h2>
<p>OAuth refresh tokens are encrypted at rest. Data is stored on servers we control. You can disconnect Google Calendar (which revokes our access) or delete your account from the Settings page at any time; deleting your account removes your profile, event types, booking records and tokens.</p>

<h2>Sharing</h2>
<p>We share data only with service providers needed to run the Service (hosting, Stripe for payments, email delivery) and when required by law. Guests' details are shared with the account holder they booked with.</p>

<h2>Retention</h2>
<p>Account data is kept while your account is active. Booking records are kept so both parties can reference them; you may request deletion by contacting us.</p>

<h2>Contact</h2>
<p>Questions or requests: <a href="mailto:<?= h($contact) ?>"><?= h($contact) ?></a>.</p>
</article>

<h1>Pricing</h1>
<div class="plans">
  <div class="card plan"><h3>Free</h3><p class="price">$0</p>
    <ul><li><?= Env::int('FREE_EVENT_TYPES', 1) ?> event type</li><li>Unlimited bookings</li><li>Google Meet links</li><li>Guest reschedule &amp; cancel</li></ul>
    <a class="btn secondary" href="/login">Start free</a></div>
  <div class="card plan featured"><h3>Pro</h3><p class="price">$8<span class="muted">/mo</span> <span class="muted small">or $72/yr</span></p>
    <ul><li>Unlimited event types</li><li>Everything in Free</li><li>Priority support</li></ul>
    <a class="btn" href="/login">Start free, upgrade anytime</a></div>
</div>
<p class="muted small mt">Cancel anytime from your billing page. Payments handled by Stripe.</p>

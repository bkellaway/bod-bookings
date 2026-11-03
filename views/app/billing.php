<?php /** @var array $user; bool $stripe; ?string $monthly, $yearly */ $pro = is_pro($user); ?>
<h1>Billing</h1>

<div class="card">
  <p>Current plan: <strong><?= $pro ? 'Pro' : 'Free' ?></strong>
    <?php if ($user['plan_status'] !== 'none'): ?><span class="pill <?= in_array($user['plan_status'], ['active', 'trialing']) ? '' : 'off' ?>"><?= h($user['plan_status']) ?></span><?php endif; ?></p>
  <?php if ($pro && $user['current_period_end']): ?>
    <p class="muted small">Renews <?= h((new DateTimeImmutable($user['current_period_end'] . ' UTC'))->setTimezone(new DateTimeZone($user['timezone']))->format('F j, Y')) ?>.
      <?php if ($user['plan_status'] === 'past_due'): ?><strong>Payment failed — update your card to keep Pro.</strong><?php endif; ?></p>
  <?php endif; ?>
  <?php if ($user['stripe_customer_id']): ?>
    <form method="post" action="/app/billing/portal" class="inline"><?= csrf_field() ?><button class="btn secondary">Manage subscription &amp; invoices</button></form>
  <?php endif; ?>
</div>

<?php if (!$pro): ?>
<h2 class="mt">Upgrade to Pro</h2>
<div class="plans">
  <div class="card plan">
    <h3>Free</h3>
    <p class="price">$0</p>
    <ul><li><?= Env::int('FREE_EVENT_TYPES', 1) ?> event type</li><li>Unlimited bookings</li><li>Google Meet links</li><li>Reschedule &amp; cancel links</li></ul>
    <p class="muted small">Your current plan</p>
  </div>
  <div class="card plan featured">
    <h3>Pro</h3>
    <p class="price">$8<span class="muted">/mo</span> <span class="muted small">or $72/yr</span></p>
    <ul><li>Unlimited event types</li><li>Everything in Free</li><li>Priority support</li></ul>
    <?php if ($stripe): ?>
      <form method="post" action="/app/billing/checkout" class="row"><?= csrf_field() ?>
        <button class="btn" name="interval" value="monthly">Monthly</button>
        <?php if ($yearly): ?><button class="btn secondary" name="interval" value="yearly">Yearly (save 25%)</button><?php endif; ?>
      </form>
    <?php else: ?>
      <p class="notice err">Billing isn't configured on this server yet.</p>
    <?php endif; ?>
  </div>
</div>
<p class="muted small">Prices shown here are text — set the real amounts on the Stripe Prices referenced in <code>.env</code>.</p>
<?php endif; ?>

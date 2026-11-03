<?php
declare(strict_types=1);

function app_billing(): void
{
    $u = Auth::require();
    if (isset($_GET['success'])) {
        flash('ok', 'Thanks! Your subscription is being activated — this page updates within a few seconds.');
        // Belt and braces: if the webhook is slow, sync from Stripe directly.
        billing_sync_from_session($u, (string)($_GET['session_id'] ?? ''));
        redirect('/app/billing');
    }
    View::render('app/billing', [
        'title' => 'Billing', 'user' => $u, 'stripe' => Stripe::enabled(),
        'monthly' => Env::get('STRIPE_PRICE_PRO_MONTHLY'), 'yearly' => Env::get('STRIPE_PRICE_PRO_YEARLY'),
    ], 'app_layout');
}

function app_billing_checkout(): void
{
    $u = Auth::require();
    csrf_check();
    if (!Stripe::enabled()) { flash('err', 'Billing is not configured.'); redirect('/app/billing'); }
    $price = ($_POST['interval'] ?? 'monthly') === 'yearly' ? Env::get('STRIPE_PRICE_PRO_YEARLY') : Env::get('STRIPE_PRICE_PRO_MONTHLY');
    if (!$price) { flash('err', 'That plan is not available.'); redirect('/app/billing'); }
    $s = Stripe::forApp()->createCheckoutSession($price, $u['email'], $u['stripe_customer_id'] ?: null, (int)$u['id'],
        app_url('app/billing?success=1&session_id={CHECKOUT_SESSION_ID}'), app_url('app/billing?cancelled=1'));
    redirect($s['url']);
}

function app_billing_portal(): void
{
    $u = Auth::require();
    csrf_check();
    if (!$u['stripe_customer_id']) { flash('err', 'No billing account yet.'); redirect('/app/billing'); }
    $s = Stripe::forApp()->createPortalSession($u['stripe_customer_id'], app_url('app/billing'));
    redirect($s['url']);
}

function billing_sync_from_session(array $u, string $sessionId): void
{
    if ($sessionId === '' || !preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) return;
    try {
        $stripe = Stripe::forApp();
        $sess = $stripe->getCheckoutSession($sessionId);
        if (($sess['client_reference_id'] ?? '') === (string)$u['id'] && !empty($sess['subscription'])) {
            billing_apply_subscription((int)$u['id'], (string)$sess['customer'], $stripe->getSubscription((string)$sess['subscription']));
        }
    } catch (Throwable $e) {
        log_error('billing sync: ' . $e->getMessage());
    }
}

/** Map a Stripe subscription object onto the user row. */
function billing_apply_subscription(int $userId, string $customerId, array $sub): void
{
    $status = (string)($sub['status'] ?? 'canceled');
    $active = in_array($status, ['active', 'trialing', 'past_due'], true);
    Models::updateUser($userId, [
        'stripe_customer_id'     => $customerId,
        'stripe_subscription_id' => (string)($sub['id'] ?? ''),
        'plan'                   => $active ? 'pro' : 'free',
        'plan_status'            => $status === 'canceled' || $status === 'unpaid' || $status === 'incomplete_expired' ? 'canceled' : $status,
        'current_period_end'     => !empty($sub['current_period_end']) ? gmdate('Y-m-d H:i:s', (int)$sub['current_period_end']) : null,
    ]);
}

/** POST /stripe/webhook */
function stripe_webhook(): void
{
    $payload = file_get_contents('php://input') ?: '';
    try {
        $event = Stripe::verifyWebhook($payload, $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '', Env::require('STRIPE_WEBHOOK_SECRET'));
    } catch (Throwable $e) {
        log_error('webhook rejected: ' . $e->getMessage());
        http_response_code(400);
        echo 'invalid';
        return;
    }
    // Idempotency
    if (DB::one('SELECT 1 FROM stripe_events WHERE id = ?', [$event['id']])) {
        echo 'duplicate';
        return;
    }
    DB::insert('stripe_events', ['id' => $event['id'], 'type' => $event['type']]);

    $obj = $event['data']['object'] ?? [];
    switch ($event['type']) {
        case 'checkout.session.completed':
            $uid = (int)($obj['client_reference_id'] ?? 0);
            if ($uid && !empty($obj['subscription']) && !empty($obj['customer'])) {
                $sub = Stripe::forApp()->getSubscription((string)$obj['subscription']);
                billing_apply_subscription($uid, (string)$obj['customer'], $sub);
            }
            break;
        case 'customer.subscription.created':
        case 'customer.subscription.updated':
        case 'customer.subscription.deleted':
            $uid = (int)($obj['metadata']['user_id'] ?? 0);
            $user = $uid ? Models::user($uid) : Models::userByStripeCustomer((string)($obj['customer'] ?? ''));
            if ($user) billing_apply_subscription((int)$user['id'], (string)$obj['customer'], $obj);
            break;
        case 'invoice.payment_failed':
            $user = Models::userByStripeCustomer((string)($obj['customer'] ?? ''));
            if ($user) Models::updateUser((int)$user['id'], ['plan_status' => 'past_due']);
            break;
    }
    echo 'ok';
}

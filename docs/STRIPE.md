# Stripe setup

1. Stripe Dashboard → **Products** → create "Pro" with two recurring prices (monthly, yearly).
   Copy the `price_...` IDs into `.env` as `STRIPE_PRICE_PRO_MONTHLY` / `STRIPE_PRICE_PRO_YEARLY`.
   Update the amounts shown on `views/pricing.php` and `views/app/billing.php` to match.
2. **Developers → API keys** → `STRIPE_SECRET_KEY` (use test keys until launch).
3. **Developers → Webhooks → Add endpoint** `https://book.backofficedesigns.com/stripe/webhook`, events:
   `checkout.session.completed`, `customer.subscription.created`, `customer.subscription.updated`,
   `customer.subscription.deleted`, `invoice.payment_failed`. Copy the signing secret → `STRIPE_WEBHOOK_SECRET`.
4. **Settings → Billing → Customer portal**: enable cancel + update payment method + invoice history; save.
5. Test with card `4242 4242 4242 4242`; run `stripe listen --forward-to localhost/stripe/webhook` locally.

How plan status flows: Checkout → webhook `checkout.session.completed` → we fetch the subscription and set
`users.plan = pro`, `plan_status = active`. Subscription updates/cancellations update the same row.
`past_due` keeps Pro features (grace) but shows a warning; `canceled` drops to Free (existing extra event
types stay but are hidden from creation — the customer keeps everything on re-subscribe).
Webhook deliveries are deduplicated in `stripe_events`.

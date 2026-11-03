# BOD Bookings — self-hosted scheduling SaaS

Multi-tenant Calendly-style booking service. Each customer signs in with Google, connects their
calendar, sets weekly hours and event types, and shares `https://book.backofficedesigns.com/{username}`.
Guests book in their own timezone; bookings become Google Calendar events with Meet links and
guest-managed reschedule/cancel links. Free and Pro plans via Stripe.

Plain PHP 8.2+, MySQL, no framework, no Composer. ~2,500 lines.

## Layout

```
public/          web root (index.php front controller, assets/)
src/             Env, DB, Crypto, Router, View, GoogleCalendar, Availability, Stripe, Mailer, Auth, Models, Booking
src/controllers/ auth.php, app.php (dashboard), public.php (booking pages + JSON API), billing.php, pages.php
views/           templates (layout.php marketing, app_layout.php dashboard, public_layout.php booking pages)
migrations/      001_init.sql (MySQL)
deploy/          nginx.conf, apache-wamp-vhost.conf
tests/           test_availability.php (unit), e2e.sh (full flow vs mock Google + Stripe, SQLite)
docs/            GOOGLE-VERIFICATION.md, STRIPE.md, DEPLOY-VPS.md
```

## Routes

| Route | What |
|---|---|
| `/`, `/pricing`, `/privacy`, `/terms` | Marketing + legal (legal pages are required by Google verification) |
| `/login`, `/auth/google`, `/auth/callback`, `POST /logout` | Sign in with Google (also grants calendar access) |
| `/app` | Dashboard: upcoming/past bookings, cancel |
| `/app/event-types`, `/app/availability`, `/app/settings`, `/app/billing` | Customer settings |
| `/{username}`, `/{username}/{slug}` | Public profile and booking page |
| `/api/slots`, `/api/book`, `/api/booking`, `/api/reschedule`, `/api/cancel` | JSON API used by booking pages |
| `/m/{token}` | Guest reschedule/cancel page (token in invite + email) |
| `POST /stripe/webhook` | Stripe events → plan status |

## Local dev (WAMP)

1. Create a MySQL database `bookings`; run `migrations/001_init.sql`.
2. `copy .env.example .env`, set `APP_URL=http://book.local`, `APP_KEY` (`php -r "echo bin2hex(random_bytes(32));"`), DB creds, Google client ID/secret.
3. Add the vhost from `deploy/apache-wamp-vhost.conf` and `127.0.0.1 book.local` to your hosts file; restart Apache.
4. Google Cloud → OAuth client → add redirect URI `http://book.local/auth/callback`.
5. Open http://book.local and sign in.

Stripe is optional locally: leave `STRIPE_*` blank and the billing page shows "not configured".
Use `stripe listen --forward-to http://book.local/stripe/webhook` from the Stripe CLI to test webhooks.

## Tests

```
php tests/test_availability.php      # slot engine (buffers, notice, horizon, DST, split days)
bash tests/e2e.sh                    # 49 checks: signup → event types → booking → reschedule → cancel → Stripe webhooks
```

## Production

See `docs/DEPLOY-VPS.md`. Short version: Ubuntu + Nginx + PHP-FPM 8.3 + MySQL, `deploy/nginx.conf`,
certbot for TLS, `.env` with `APP_ENV=production`, `MAIL_DRIVER=mail` with a configured MTA (or swap `Mailer.php` for an API mailer).

## Security notes

- Refresh tokens encrypted with libsodium (`APP_KEY`). Rotating `APP_KEY` invalidates all tokens (users reconnect).
- CSRF tokens on every dashboard POST; sessions are HttpOnly/SameSite=Lax/Secure.
- Manage links are 256-bit random tokens; no IDs are guessable.
- Stripe webhooks are signature-verified and idempotent (`stripe_events`).
- Guests never authenticate; the booking API re-checks free/busy at write time to prevent double booking.

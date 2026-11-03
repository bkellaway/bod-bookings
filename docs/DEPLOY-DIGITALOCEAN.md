# Deploy to DigitalOcean in one paste

`deploy/digitalocean-cloud-init.sh` is a self-contained bootstrap: the whole app is embedded in it.
Pasting it as the droplet's **User data** produces a fully configured server — no SSH, no scp.

## 1. Before creating the droplet
- Open `deploy/digitalocean-cloud-init.sh` in a text editor and fill the block at the top:
  `DOMAIN`, `ADMIN_EMAIL`, `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` (Stripe values can stay blank for now).
- Google Cloud → your OAuth client → add redirect URI `https://book.backofficedesigns.com/auth/callback`.

## 2. Create the droplet (cloud.digitalocean.com → Create → Droplets)
- Region: San Francisco (closest to Carlsbad).
- Image: **Ubuntu 24.04 (LTS) x64**.
- Size: Basic → Regular → **$6/mo (1 vCPU, 1 GB)** is plenty to start.
- Authentication: **SSH key** (add yours; you'll want it for later maintenance).
- **Advanced Options → "Add Initialization scripts (free)"** → paste the *entire* contents of the edited script.
- Hostname: `book-backofficedesigns`. Create.

## 3. DNS
When the droplet shows its IPv4 address, add an **A record** `book` → that IP wherever backofficedesigns.com's DNS lives.
The server checks every 5 minutes; as soon as the A record resolves to itself it obtains the Let's Encrypt
certificate and switches to HTTPS automatically. Until then `http://<ip>/` serves the site (Google sign-in
won't work over the bare IP because the redirect URI is the https domain — that's expected).

## 4. Verify
- `https://book.backofficedesigns.com/` → landing page.
- Sign in with Google → dashboard → create an event type → book yourself from another browser.
- Bootstrap log if anything looks off: `ssh root@<ip> cat /var/log/bookings-bootstrap.log`
  (a file `/var/www/bookings-bootstrap.done` exists when it finished cleanly).

## Later changes
- Edit settings: `ssh root@<ip>` → `nano /var/www/bookings/.env` (Stripe keys, MAIL_DRIVER) → `systemctl reload php8.3-fpm`.
- Deploy new code: `scp -r public src views migrations root@<ip>:/var/www/bookings/` then
  `ssh root@<ip> "chown -R www-data:www-data /var/www/bookings"`. New migrations: `mysql bookings < migrations/00N_*.sql`.
- Backups: nightly `mysqldump` gzips in `/var/backups/bookings/` (14 kept). Also copy `.env` somewhere safe — `APP_KEY` decrypts every user's calendar token.
- Email is `MAIL_DRIVER=log` (invites still come from Google). For confirmation emails, set up Postmark/SES and swap `src/Mailer.php`, or install postfix and set `MAIL_DRIVER=mail`.

## Regenerating the script
If you change the code, rebuild the payload from the `bookings` folder:
`tar czf /tmp/app.tgz --exclude=tests --exclude=docs --exclude=README.md --exclude='*.zip' --exclude=.gitignore --exclude=deploy/apache-wamp-vhost.conf --exclude=public/.htaccess . && base64 -w0 /tmp/app.tgz`
and replace everything after the `__PAYLOAD__` line. Keep the whole file under 64 KB (DigitalOcean's user-data limit).

# Deploy to Cloudways

Cloudways runs Nginx → Apache → PHP-FPM with MySQL, handles SSL and backups in the panel, and gives you
SFTP/SSH. No root, no server admin. Everything the app needs (PHP 8.2+, sodium, curl, mbstring, pdo_mysql)
is included in Cloudways' PHP builds. Nothing in the code changes.

## 1. Create the application (≈5 min)
1. Cloudways → **Applications → Add Application** (or during server launch).
   - Application: **PHP** (the "Custom PHP" stack, not WordPress/Laravel).
   - Name: `bookings`. Server: DigitalOcean 1 GB is fine to start (~$11–14/mo on Cloudways).
2. **Server → Settings & Packages → Packages** → PHP **8.2** or **8.3** (8.1+ works; 8.3 preferred).

## 2. Upload the app
1. **Application → Access Details** → note the *Application credentials* (SFTP user/pass) and *MySQL access* (DB name, user, password).
2. Connect with FileZilla/WinSCP (SFTP, port 22) using the application credentials.
3. Inside `public_html/` **delete the sample index.php**, then upload the contents of your `bookings` folder so you end up with:
   ```
   public_html/public/     ← index.php, .htaccess, assets/
   public_html/src/
   public_html/views/
   public_html/migrations/
   public_html/.env        ← created in step 3
   ```
   (`tests/`, `docs/`, `deploy/` are optional; leave them out.)

## 3. Configure
1. Create `public_html/.env` from `.env.example`:
   ```
   APP_NAME="BOD Bookings"
   APP_URL=https://book.backofficedesigns.com
   APP_ENV=production
   APP_KEY=<64 hex chars — php -r "echo bin2hex(random_bytes(32));" on your PC, or any 64-char hex string from a generator>
   DB_DSN="mysql:host=127.0.0.1;dbname=<DB name from Access Details>;charset=utf8mb4"
   DB_USER=<DB user>
   DB_PASS=<DB password>
   GOOGLE_CLIENT_ID=...
   GOOGLE_CLIENT_SECRET=...
   MAIL_DRIVER=log
   MAIL_FROM=hello@backofficedesigns.com
   MAIL_FROM_NAME="BOD Bookings"
   FREE_EVENT_TYPES=1
   ```
   Stripe lines can be blank until you're ready.
   `.env` sits in `public_html/` which is **outside** the webroot after step 3.2, so it's not web-accessible.
2. **Application → Application Settings → General → Webroot** → enter `public` → Save.
   (Cloudways docs: [How to Change the Web Root of an Application](https://support.cloudways.com/en/articles/5124748-how-to-change-the-web-root-of-an-application).)
3. Database: **Access Details → Launch Database Manager** → SQL tab → paste the contents of `migrations/001_init.sql` → Go.
4. Check: open the Cloudways temporary app URL (`https://phpstack-…cloudwaysapps.com`) — landing page should render.
   Google sign-in won't work on that URL (redirect URI mismatch); that's expected until the domain is set.

## 4. Domain + SSL
1. DNS at your registrar: **A record** `book` → the server's public IP (shown on the Server page).
   (CNAME to the cloudwaysapps hostname also works.)
2. **Application → Domain Management** → add `book.backofficedesigns.com` → make it **Primary**.
3. **Application → SSL Certificate** → Let's Encrypt → enter the domain → Install. Turn on auto-renew.
4. Google Cloud → OAuth client → Authorized redirect URI `https://book.backofficedesigns.com/auth/callback`.
5. Visit the domain → Sign in with Google → create an event type → book yourself from another browser.

## 5. Email (optional but recommended)
Cloudways sends `mail()` through whatever SMTP you configure: **Server → SMTP** (Elastic Email add-on, or your
own SMTP such as Google Workspace's `smtp-relay.gmail.com`). Once that's set, change `.env` to `MAIL_DRIVER=mail`.
Google Calendar invitations reach guests regardless of this setting.

## 6. Stripe (when ready)
Follow `docs/STRIPE.md`; the webhook URL is `https://book.backofficedesigns.com/stripe/webhook`.

## Ongoing
- **Updates:** upload changed files over SFTP (or set up **Deployment via Git** in the app panel if you push the code to a repo).
  New migrations: paste into the Database Manager.
- **Backups:** Cloudways server backups cover files + DB; keep a private copy of `.env` too — `APP_KEY` decrypts every customer's calendar token.
- **Logs:** Application → **Monitoring → Logs** (Apache error log) shows `[bookings]` errors and `[mail]` lines while `MAIL_DRIVER=log`.
- **Permissions:** if you ever see 403/500 after uploading, Application → **Application Settings → Reset Permissions**.
- **Staging:** Cloudways' one-click **Clone/Staging** works; set a separate `APP_URL` and Google redirect URI for it.

# VPS deployment (Ubuntu 24.04)

```bash
# packages
sudo apt update && sudo apt install -y nginx php8.3-fpm php8.3-mysql php8.3-curl php8.3-mbstring php8.3-xml mysql-server certbot python3-certbot-nginx unzip
# app
sudo mkdir -p /var/www/bookings && sudo chown $USER /var/www/bookings
# upload the repo (git clone or scp), then:
cd /var/www/bookings && cp .env.example .env && nano .env      # APP_URL, APP_KEY, DB, Google, Stripe, MAIL
# database
sudo mysql -e "CREATE DATABASE bookings CHARACTER SET utf8mb4; CREATE USER 'bookings'@'localhost' IDENTIFIED BY 'CHANGE'; GRANT ALL ON bookings.* TO 'bookings'@'localhost';"
mysql -u bookings -p bookings < migrations/001_init.sql
# permissions: only .env needs to be readable by php-fpm; nothing is written to disk by the app
sudo chown -R www-data:www-data /var/www/bookings && sudo chmod 640 /var/www/bookings/.env
# nginx + TLS
sudo cp deploy/nginx.conf /etc/nginx/sites-available/book.backofficedesigns.com
sudo ln -s /etc/nginx/sites-available/book.backofficedesigns.com /etc/nginx/sites-enabled/
sudo certbot --nginx -d book.backofficedesigns.com
sudo nginx -t && sudo systemctl reload nginx
```

DNS: `book` A record → VPS IP (do this before certbot).

Email: `MAIL_DRIVER=mail` uses the server MTA. Install `postfix` (satellite) relaying through your
Workspace SMTP or a transactional provider, or replace `src/Mailer.php` with a Postmark/SES HTTP call
(one function). Google Calendar invitations are sent by Google regardless.

PHP settings worth setting in `/etc/php/8.3/fpm/php.ini`: `session.cookie_secure=1`, `expose_php=Off`.

Backups: `mysqldump bookings` nightly + `.env` (which holds `APP_KEY` — without it tokens are unreadable).

Logs: `/var/log/nginx/error.log` receives `error_log()` output from PHP-FPM; `[bookings]` prefix and `[mail]` lines.

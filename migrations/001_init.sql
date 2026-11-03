-- MySQL 8 / MariaDB 10.5+
CREATE TABLE users (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email            VARCHAR(254) NOT NULL UNIQUE,
  name             VARCHAR(120) NOT NULL,
  username         VARCHAR(40)  NOT NULL UNIQUE,
  google_sub       VARCHAR(64)  NOT NULL UNIQUE,
  timezone         VARCHAR(64)  NOT NULL DEFAULT 'America/Los_Angeles',
  availability_json JSON        NOT NULL,
  rules_json       JSON         NOT NULL,
  welcome_text     VARCHAR(500) NOT NULL DEFAULT '',
  plan             VARCHAR(16)  NOT NULL DEFAULT 'free',        -- free | pro
  plan_status      VARCHAR(24)  NOT NULL DEFAULT 'none',        -- none | active | trialing | past_due | canceled
  stripe_customer_id     VARCHAR(64) NULL,
  stripe_subscription_id VARCHAR(64) NULL,
  current_period_end     DATETIME NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (stripe_customer_id)
);

CREATE TABLE google_tokens (
  user_id          INT UNSIGNED PRIMARY KEY,
  refresh_token_enc TEXT        NOT NULL,       -- libsodium secretbox, base64
  access_token     TEXT         NULL,
  expires_at       INT UNSIGNED NOT NULL DEFAULT 0,
  calendar_id      VARCHAR(255) NOT NULL DEFAULT 'primary',
  busy_calendars_json JSON      NOT NULL,
  scope            VARCHAR(500) NOT NULL DEFAULT '',
  revoked          TINYINT(1)   NOT NULL DEFAULT 0,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_gt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE event_types (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  slug             VARCHAR(60)  NOT NULL,
  name             VARCHAR(120) NOT NULL,
  duration         SMALLINT UNSIGNED NOT NULL,
  description      VARCHAR(500) NOT NULL DEFAULT '',
  meet             TINYINT(1)   NOT NULL DEFAULT 1,
  active           TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order       SMALLINT     NOT NULL DEFAULT 0,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_slug (user_id, slug),
  CONSTRAINT fk_et_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE bookings (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id          INT UNSIGNED NOT NULL,
  event_type_id    INT UNSIGNED NULL,
  event_type_name  VARCHAR(120) NOT NULL,
  duration         SMALLINT UNSIGNED NOT NULL,
  google_event_id  VARCHAR(255) NULL,
  meet_link        VARCHAR(255) NULL,
  guest_name       VARCHAR(120) NOT NULL,
  guest_email      VARCHAR(254) NOT NULL,
  guest_tz         VARCHAR(64)  NOT NULL,
  notes            TEXT         NULL,
  start_utc        DATETIME     NOT NULL,
  end_utc          DATETIME     NOT NULL,
  status           VARCHAR(16)  NOT NULL DEFAULT 'confirmed',   -- confirmed | cancelled
  manage_token     CHAR(43)     NOT NULL UNIQUE,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_user_start (user_id, start_utc),
  CONSTRAINT fk_b_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_b_et   FOREIGN KEY (event_type_id) REFERENCES event_types(id) ON DELETE SET NULL
);

CREATE TABLE stripe_events (
  id               VARCHAR(64) PRIMARY KEY,       -- evt_..., idempotency
  type             VARCHAR(64) NOT NULL,
  received_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

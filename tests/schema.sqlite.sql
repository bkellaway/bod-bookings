-- SQLite equivalent of migrations/001_init.sql (tests / local dev only)
CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE, name TEXT NOT NULL, username TEXT NOT NULL UNIQUE,
  google_sub TEXT NOT NULL UNIQUE, timezone TEXT NOT NULL DEFAULT 'America/Los_Angeles',
  availability_json TEXT NOT NULL, rules_json TEXT NOT NULL, welcome_text TEXT NOT NULL DEFAULT '',
  plan TEXT NOT NULL DEFAULT 'free', plan_status TEXT NOT NULL DEFAULT 'none',
  stripe_customer_id TEXT NULL, stripe_subscription_id TEXT NULL, current_period_end TEXT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE google_tokens (
  user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE, refresh_token_enc TEXT NOT NULL, access_token TEXT NULL,
  expires_at INTEGER NOT NULL DEFAULT 0, calendar_id TEXT NOT NULL DEFAULT 'primary', busy_calendars_json TEXT NOT NULL,
  scope TEXT NOT NULL DEFAULT '', revoked INTEGER NOT NULL DEFAULT 0, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE event_types (
  id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE, slug TEXT NOT NULL, name TEXT NOT NULL,
  duration INTEGER NOT NULL, description TEXT NOT NULL DEFAULT '', meet INTEGER NOT NULL DEFAULT 1, active INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, slug));
CREATE TABLE bookings (
  id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  event_type_id INTEGER NULL REFERENCES event_types(id) ON DELETE SET NULL, event_type_name TEXT NOT NULL, duration INTEGER NOT NULL,
  google_event_id TEXT NULL, meet_link TEXT NULL, guest_name TEXT NOT NULL, guest_email TEXT NOT NULL, guest_tz TEXT NOT NULL, notes TEXT NULL,
  start_utc TEXT NOT NULL, end_utc TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'confirmed', manage_token TEXT NOT NULL UNIQUE,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE stripe_events (id TEXT PRIMARY KEY, type TEXT NOT NULL, received_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);

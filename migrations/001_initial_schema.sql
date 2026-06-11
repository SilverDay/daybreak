-- Daybreak — 001_initial_schema.sql
-- MariaDB 10.11+ · InnoDB · utf8mb4. Full schema per docs/SPEC.md §9.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Migration tracking ------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_migrations (
  filename   VARCHAR(255) PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users & auth ------------------------------------------------------------
CREATE TABLE users (
  id                  BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  email               VARCHAR(254) NOT NULL UNIQUE,
  password_hash       VARCHAR(255) NOT NULL,
  display_name        VARCHAR(80)  NOT NULL,
  role                ENUM('user','admin') NOT NULL DEFAULT 'user',
  status              ENUM('pending','active','disabled') NOT NULL DEFAULT 'pending',
  default_window_days TINYINT UNSIGNED NOT NULL DEFAULT 1,
  email_verified_at   DATETIME NULL,
  last_login_at       DATETIME NULL,
  last_seen_at        DATETIME NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE auth_tokens (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  type        ENUM('password_reset','email_verify') NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_token_hash (token_hash),
  KEY idx_user_type (user_id, type),
  CONSTRAINT fk_authtok_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sessions (
  id            CHAR(64) PRIMARY KEY,
  user_id       BIGINT UNSIGNED NULL,
  ip_hash       CHAR(64) NULL,
  user_agent    VARCHAR(255) NULL,
  payload       MEDIUMTEXT NOT NULL,
  last_activity DATETIME NOT NULL,
  KEY idx_last_activity (last_activity),
  CONSTRAINT fk_sess_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  email       VARCHAR(254) NULL,
  ip_hash     CHAR(64) NOT NULL,
  successful  TINYINT(1) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip_time (ip_hash, created_at),
  KEY idx_email_time (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sources & catalogue -----------------------------------------------------
CREATE TABLE source_categories (
  id         INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(64) NOT NULL,
  slug       VARCHAR(64) NOT NULL UNIQUE,
  color      VARCHAR(7)  NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sources (
  id                   INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name                 VARCHAR(120) NOT NULL,
  slug                 VARCHAR(120) NOT NULL UNIQUE,
  homepage_url         VARCHAR(500) NOT NULL,
  feed_url             VARCHAR(500) NULL,
  adapter_type         ENUM('rss_atom','json_api','ransomlook','nvd','html_scrape') NOT NULL,
  field_map            JSON NULL,
  category_id          INT UNSIGNED NULL,
  attribution_text     VARCHAR(255) NOT NULL,
  license              VARCHAR(120) NULL,
  status               ENUM('pending','active','disabled','degraded','auto_disabled') NOT NULL DEFAULT 'pending',
  fetch_interval_min   SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  etag                 VARCHAR(255) NULL,
  last_modified_hdr    VARCHAR(64) NULL,
  next_fetch_at        DATETIME NULL,
  last_fetch_at        DATETIME NULL,
  last_success_at      DATETIME NULL,
  last_error           VARCHAR(500) NULL,
  consecutive_failures SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_by           BIGINT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status_next (status, next_fetch_at),
  CONSTRAINT fk_source_cat FOREIGN KEY (category_id) REFERENCES source_categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_source_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Articles ----------------------------------------------------------------
CREATE TABLE articles (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  source_id     INT UNSIGNED NOT NULL,
  guid          VARCHAR(500) NOT NULL,
  title         VARCHAR(500) NOT NULL,
  url           VARCHAR(1000) NOT NULL,
  summary       TEXT NULL,
  published_at  DATETIME NULL,
  fetched_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dedup_key     CHAR(40) NULL,
  UNIQUE KEY uq_source_guid (source_id, guid),
  KEY idx_published (published_at),
  KEY idx_dedup (dedup_key),
  CONSTRAINT fk_article_source FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-user customisation --------------------------------------------------
CREATE TABLE user_sources (
  user_id   BIGINT UNSIGNED NOT NULL,
  source_id INT UNSIGNED NOT NULL,
  enabled   TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (user_id, source_id),
  CONSTRAINT fk_us_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
  CONSTRAINT fk_us_source FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suggestions -------------------------------------------------------------
CREATE TABLE source_suggestions (
  id               BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  suggested_by     BIGINT UNSIGNED NULL,
  name             VARCHAR(120) NOT NULL,
  homepage_url     VARCHAR(500) NOT NULL,
  feed_url         VARCHAR(500) NULL,
  detected_adapter VARCHAR(32) NULL,
  note             VARCHAR(500) NULL,
  status           ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by      BIGINT UNSIGNED NULL,
  reviewed_at      DATETIME NULL,
  review_note      VARCHAR(500) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status (status),
  CONSTRAINT fk_sug_user     FOREIGN KEY (suggested_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_sug_reviewer FOREIGN KEY (reviewed_by)  REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Operations / observability ---------------------------------------------
CREATE TABLE fetch_log (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  source_id   INT UNSIGNED NOT NULL,
  status      ENUM('ok','not_modified','error') NOT NULL,
  http_status SMALLINT UNSIGNED NULL,
  items_found SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  items_new   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  duration_ms INT UNSIGNED NULL,
  error       VARCHAR(500) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_source_time (source_id, created_at),
  CONSTRAINT fk_fetchlog_source FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_log (
  id          BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NULL,
  action      VARCHAR(80) NOT NULL,
  target_type VARCHAR(40) NULL,
  target_id   VARCHAR(64) NULL,
  ip_hash     CHAR(64) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_time (user_id, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed categories (mirrors the daily-briefing taxonomy, SPEC §6.9) ---------
INSERT INTO source_categories (name, slug, color, sort_order) VALUES
  ('Critical / Patch Now', 'critical',    '#c0392b', 10),
  ('Threat Intel',         'threat-intel','#2c3e50', 20),
  ('Strategic',            'strategic',   '#3498db', 30),
  ('DACH Corner',          'dach',        '#27ae60', 40),
  ('Privacy',              'privacy',     '#8e44ad', 50),
  ('Ransomware',           'ransomware',  '#000000', 60),
  ('EU / Policy',          'eu-policy',   '#16a085', 70);

SET FOREIGN_KEY_CHECKS = 1;

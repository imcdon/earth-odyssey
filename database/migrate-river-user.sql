-- migrate-river-user.sql - Public angler accounts, saved rivers, personal ranges, alerts, auto ranges.
-- Safe to re-run. No USE statement: on cPanel, select your database in phpMyAdmin first;
-- locally: C:\xampp\mysql\bin\mysql.exe -u root earth_odyssey < database/migrate-river-user.sql

ALTER TABLE users
    MODIFY role ENUM('author', 'editor', 'member') NOT NULL DEFAULT 'author';

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email VARCHAR(190) NULL AFTER username,
    ADD COLUMN IF NOT EXISTS display_name VARCHAR(100) NOT NULL DEFAULT '' AFTER email,
    ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL AFTER display_name,
    ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER phone,
    ADD COLUMN IF NOT EXISTS phone_verified_at DATETIME NULL AFTER email_verified_at;

-- Unique email when set (MySQL allows multiple NULLs).
CREATE UNIQUE INDEX IF NOT EXISTS users_email_unique ON users (email);

CREATE TABLE IF NOT EXISTS river_user_sites (
    user_id INT NOT NULL,
    site_id VARCHAR(32) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, site_id),
    INDEX idx_user_sort (user_id, sort_order),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS river_user_ranges (
    user_id INT NOT NULL,
    site_id VARCHAR(32) NOT NULL,
    param CHAR(5) NOT NULL,
    fish_low DOUBLE NULL,
    ideal_low DOUBLE NULL,
    ideal_high DOUBLE NULL,
    fish_high DOUBLE NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (user_id, site_id, param),
    INDEX idx_site (site_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS river_alert_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    site_id VARCHAR(32) NOT NULL,
    param CHAR(5) NOT NULL DEFAULT '00060',
    trigger_enter_prime TINYINT(1) NOT NULL DEFAULT 1,
    trigger_leave_fishable TINYINT(1) NOT NULL DEFAULT 1,
    trigger_above_high TINYINT(1) NOT NULL DEFAULT 1,
    trigger_below_low TINYINT(1) NOT NULL DEFAULT 1,
    trigger_unusual TINYINT(1) NOT NULL DEFAULT 0,
    channel_in_app TINYINT(1) NOT NULL DEFAULT 1,
    channel_email TINYINT(1) NOT NULL DEFAULT 1,
    channel_sms TINYINT(1) NOT NULL DEFAULT 0,
    cooldown_minutes INT NOT NULL DEFAULT 360,
    last_level VARCHAR(16) NULL,
    last_fired_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_site_param (user_id, site_id, param),
    INDEX idx_site (site_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS river_alert_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    site_id VARCHAR(32) NOT NULL,
    param CHAR(5) NOT NULL,
    trigger_key VARCHAR(32) NOT NULL,
    level VARCHAR(16) NULL,
    value DOUBLE NULL,
    message VARCHAR(500) NOT NULL,
    channel_in_app TINYINT(1) NOT NULL DEFAULT 1,
    channel_email TINYINT(1) NOT NULL DEFAULT 0,
    channel_sms TINYINT(1) NOT NULL DEFAULT 0,
    email_sent_at DATETIME NULL,
    sms_sent_at DATETIME NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_unread (user_id, read_at, created_at),
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS river_auto_ranges (
    site_id VARCHAR(32) NOT NULL,
    param CHAR(5) NOT NULL,
    fish_low DOUBLE NULL,
    ideal_low DOUBLE NULL,
    ideal_high DOUBLE NULL,
    fish_high DOUBLE NULL,
    method VARCHAR(32) NOT NULL DEFAULT 'percentile',
    method_version VARCHAR(64) NOT NULL DEFAULT '',
    n_sample INT NOT NULL DEFAULT 0,
    stats JSON NULL,
    computed_at DATETIME NOT NULL,
    PRIMARY KEY (site_id, param),
    INDEX idx_computed (computed_at)
);

CREATE TABLE IF NOT EXISTS river_password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_token (token_hash),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

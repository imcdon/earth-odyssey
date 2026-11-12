-- schema.sql - Earth Odyssey articles database tables.
-- Local/XAMPP only. On cPanel use schema-cpanel.sql (select your DB first; no CREATE DATABASE).
CREATE DATABASE IF NOT EXISTS earth_odyssey CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE earth_odyssey;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(190) NULL,
    display_name VARCHAR(100) NOT NULL DEFAULT '',
    phone VARCHAR(20) NULL,
    email_verified_at DATETIME NULL,
    phone_verified_at DATETIME NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('author', 'editor', 'member') NOT NULL DEFAULT 'author',
    account_status ENUM('pending', 'approved', 'denied') NOT NULL DEFAULT 'pending',
    perm_library TINYINT(1) NOT NULL DEFAULT 0,
    perm_tools TINYINT(1) NOT NULL DEFAULT 0,
    perm_own_ranges TINYINT(1) NOT NULL DEFAULT 0,
    perm_fishing_reports TINYINT(1) NOT NULL DEFAULT 0,
    reviewed_at DATETIME NULL,
    reviewed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY users_email_unique (email)
);

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('article', 'gallery') NOT NULL DEFAULT 'article',
    slug VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY unique_type_slug (type, slug)
);

CREATE TABLE IF NOT EXISTS articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    blurb VARCHAR(500) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('draft', 'pending', 'published', 'rejected') NOT NULL DEFAULT 'draft',
    author_id INT NOT NULL,
    editor_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at TIMESTAMP NULL,
    category_id INT NULL,
    thumbnail VARCHAR(255) NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (author_id) REFERENCES users(id),
    FOREIGN KEY (editor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FULLTEXT INDEX ft_articles_search (title, blurb, body)
);

CREATE TABLE IF NOT EXISTS river_api_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(40) NOT NULL,
    status SMALLINT NOT NULL DEFAULT 0,
    cache_hit TINYINT(1) NOT NULL DEFAULT 0,
    stale TINYINT(1) NOT NULL DEFAULT 0,
    ms INT NOT NULL DEFAULT 0,
    bytes INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at)
);

CREATE TABLE IF NOT EXISTS river_sites (
    site_id VARCHAR(32) PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    display_name VARCHAR(200) NOT NULL,
    state CHAR(2) NOT NULL,
    county VARCHAR(100) NULL,
    site_type VARCHAR(10) NULL,
    lat DECIMAL(9,6) NULL,
    lon DECIMAL(9,6) NULL,
    params VARCHAR(64) NOT NULL DEFAULT '',
    last_reading_at DATETIME NULL,
    daily_synced_at DATETIME NULL,
    synced_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_state_name (state, display_name),
    INDEX idx_display_name (display_name)
);

CREATE TABLE IF NOT EXISTS river_daily (
    site_id VARCHAR(32) NOT NULL,
    param CHAR(5) NOT NULL,
    day DATE NOT NULL,
    value DOUBLE NOT NULL,
    PRIMARY KEY (site_id, param, day)
);

CREATE TABLE IF NOT EXISTS river_site_views (
    site_id VARCHAR(32) NOT NULL,
    viewed_on DATE NOT NULL,
    views INT NOT NULL DEFAULT 0,
    PRIMARY KEY (site_id, viewed_on),
    INDEX idx_viewed_on (viewed_on)
);

CREATE TABLE IF NOT EXISTS river_snapshots (
    site_id VARCHAR(32) NOT NULL,
    param CHAR(5) NOT NULL,
    observed_at DATETIME NOT NULL,
    value DOUBLE NOT NULL,
    PRIMARY KEY (site_id, param, observed_at),
    INDEX idx_observed_at (observed_at)
);

CREATE TABLE IF NOT EXISTS river_movers (
    state CHAR(2) NOT NULL,
    win VARCHAR(3) NOT NULL,
    param CHAR(5) NOT NULL,
    direction VARCHAR(4) NOT NULL,
    rank_no TINYINT NOT NULL,
    site_id VARCHAR(32) NOT NULL,
    value_then DOUBLE NOT NULL,
    value_now DOUBLE NOT NULL,
    delta DOUBLE NOT NULL,
    pct DOUBLE NULL,
    observed_then DATETIME NOT NULL,
    observed_now DATETIME NOT NULL,
    spark VARCHAR(1000) NOT NULL DEFAULT '[]',
    computed_at DATETIME NOT NULL,
    PRIMARY KEY (state, win, param, direction, rank_no)
);

CREATE TABLE IF NOT EXISTS river_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    trip_date DATE NOT NULL,
    start_time TIME NULL,
    end_time TIME NULL,
    angler VARCHAR(120) NOT NULL DEFAULT '',
    measure_units VARCHAR(6) NOT NULL DEFAULT 'us',
    clarity VARCHAR(20) NOT NULL DEFAULT '',
    crowding VARCHAR(20) NOT NULL DEFAULT '',
    access_point VARCHAR(200) NOT NULL DEFAULT '',
    hatch TEXT NULL,
    tackle TEXT NULL,
    writeup MEDIUMTEXT NULL,
    conditions_json MEDIUMTEXT NULL,
    conditions_key CHAR(32) NULL,
    conditions_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_author (author_id),
    INDEX idx_trip_date (trip_date),
    FOREIGN KEY (author_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS river_report_sites (
    report_id INT NOT NULL,
    position TINYINT NOT NULL,
    site_id VARCHAR(32) NOT NULL,
    PRIMARY KEY (report_id, position),
    INDEX idx_site (site_id),
    FOREIGN KEY (report_id) REFERENCES river_reports(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS river_report_catches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    position SMALLINT NOT NULL,
    species VARCHAR(80) NOT NULL DEFAULT '',
    fish_count SMALLINT NULL,
    length DECIMAL(6,2) NULL,
    weight DECIMAL(6,2) NULL,
    kept TINYINT(1) NULL,
    fly VARCHAR(120) NOT NULL DEFAULT '',
    caught_at TIME NULL,
    note VARCHAR(255) NOT NULL DEFAULT '',
    INDEX idx_report (report_id, position),
    FOREIGN KEY (report_id) REFERENCES river_reports(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS river_report_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    position SMALLINT NOT NULL,
    file VARCHAR(80) NOT NULL,
    mime VARCHAR(20) NOT NULL,
    width SMALLINT NOT NULL,
    height SMALLINT NOT NULL,
    caption VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_report (report_id, position),
    FOREIGN KEY (report_id) REFERENCES river_reports(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS river_ranges (
    site_id VARCHAR(32) NOT NULL,
    param CHAR(5) NOT NULL,
    fish_low DOUBLE NULL,
    ideal_low DOUBLE NULL,
    ideal_high DOUBLE NULL,
    fish_high DOUBLE NULL,
    updated_by INT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (site_id, param),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

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

CREATE TABLE IF NOT EXISTS books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(32) NOT NULL DEFAULT 'gutenberg',
    source_id VARCHAR(64) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(200) NOT NULL DEFAULT '',
    year SMALLINT NULL,
    subjects VARCHAR(200) NOT NULL DEFAULT '',
    blurb VARCHAR(500) NOT NULL DEFAULT '',
    cover_path VARCHAR(255) NOT NULL DEFAULT '',
    html_path VARCHAR(255) NULL,
    epub_path VARCHAR(255) NULL,
    source_url VARCHAR(255) NOT NULL DEFAULT '',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slug (slug),
    UNIQUE KEY unique_source (source, source_id),
    INDEX idx_featured (is_featured, sort_order)
);

CREATE TABLE IF NOT EXISTS book_search_cache (
    cache_key CHAR(40) NOT NULL PRIMARY KEY,
    payload MEDIUMTEXT NOT NULL,
    fetched_at DATETIME NOT NULL,
    INDEX idx_fetched (fetched_at)
);

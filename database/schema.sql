-- schema.sql - Earth Odyssey articles database tables.
-- Local/XAMPP only. On cPanel use schema-cpanel.sql (select your DB first; no CREATE DATABASE).
CREATE DATABASE IF NOT EXISTS earth_odyssey CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE earth_odyssey;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('author', 'editor') NOT NULL DEFAULT 'author',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
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

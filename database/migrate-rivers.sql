-- migrate-rivers.sql - Tables for the river tool (/services/rivers/).
-- Safe to re-run. No USE statement: on cPanel, select your database in phpMyAdmin first;
-- locally: C:\xampp\mysql\bin\mysql.exe -u root earth_odyssey < database/migrate-rivers.sql

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

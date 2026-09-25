-- migrate-reports.sql - Fishing report tables for the river tool (admin/river-reports.php).
-- Safe to re-run. No USE statement: on cPanel, select your database in phpMyAdmin first;
-- locally: C:\xampp\mysql\bin\mysql.exe -u root earth_odyssey < database/migrate-reports.sql

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

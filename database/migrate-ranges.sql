-- migrate-ranges.sql - Fishability ranges per river (admin/river-ranges.php).
-- Safe to re-run. No USE statement: on cPanel, select your database in phpMyAdmin first;
-- locally: C:\xampp\mysql\bin\mysql.exe -u root earth_odyssey < database/migrate-ranges.sql

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

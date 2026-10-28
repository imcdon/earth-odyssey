-- migrate-books.sql - Curated public-domain shelf and catalog search cache (/books/).
-- Safe to re-run. No USE statement: on cPanel, select your database in phpMyAdmin first;
-- locally: C:\xampp\mysql\bin\mysql.exe -u root earth_odyssey < database/migrate-books.sql

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

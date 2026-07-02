-- migrate-articles-page.sql - Upgrade existing earth_odyssey DB for articles page redesign.
-- Run once: mysql -u root earth_odyssey < database/migrate-articles-page.sql
USE earth_odyssey;

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0
);

INSERT IGNORE INTO categories (slug, name, image_path, sort_order) VALUES
('hiking', 'Hiking', '/assets/img/categories/hiking.svg', 1),
('camping', 'Camping', '/assets/img/categories/camping.svg', 2),
('fishing', 'Fishing', '/assets/img/categories/fishing.svg', 3),
('hunting', 'Hunting', '/assets/img/categories/hunting.svg', 4),
('weather', 'Weather', '/assets/img/categories/weather.svg', 5);

ALTER TABLE articles
    ADD COLUMN IF NOT EXISTS category_id INT NULL AFTER published_at,
    ADD COLUMN IF NOT EXISTS thumbnail VARCHAR(255) NULL AFTER category_id,
    ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER thumbnail;

UPDATE articles SET category_id = 1, thumbnail = '/assets/img/hero/day-hike.svg', is_featured = 1
WHERE slug = 'packing-day-hike';

UPDATE articles SET category_id = 2, thumbnail = '/assets/img/hero/autumn-camp.svg', is_featured = 0
WHERE slug = 'first-post';

UPDATE articles SET category_id = 5, thumbnail = '/assets/img/hero/weather.svg', is_featured = 0
WHERE slug = 'reading-weather';

UPDATE articles SET is_featured = 0 WHERE slug != 'packing-day-hike';

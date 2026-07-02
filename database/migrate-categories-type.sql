-- migrate-categories-type.sql - Split categories into article vs gallery types.
-- Run once: mysql -u root earth_odyssey < database/migrate-categories-type.sql
USE earth_odyssey;

ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS type ENUM('article', 'gallery') NOT NULL DEFAULT 'article' AFTER id;

UPDATE categories SET type = 'article' WHERE type IS NULL OR type = '';

ALTER TABLE categories DROP INDEX IF EXISTS slug;
ALTER TABLE categories ADD UNIQUE KEY unique_type_slug (type, slug);

INSERT IGNORE INTO categories (type, slug, name, image_path, sort_order) VALUES
('gallery', 'landscapes', 'Landscapes', '/assets/img/categories/gallery-landscapes.svg', 1),
('gallery', 'wildlife', 'Wildlife', '/assets/img/categories/gallery-wildlife.svg', 2),
('gallery', 'campsite', 'Campsite', '/assets/img/categories/gallery-campsite.svg', 3),
('gallery', 'on-the-water', 'On the Water', '/assets/img/categories/gallery-water.svg', 4);

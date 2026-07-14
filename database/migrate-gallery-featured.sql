-- migrate-gallery-featured.sql - Featured gallery category for home carousel + galleries page.
USE earth_odyssey;

ALTER TABLE categories
    ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_order;

-- One featured gallery at a time: Campsite matches the previous autumn-camp carousel theme.
UPDATE categories SET is_featured = 0 WHERE type = 'gallery';
UPDATE categories
SET is_featured = 1,
    image_path = '/assets/img/hero/autumn-camp.webp'
WHERE type = 'gallery' AND slug = 'campsite';

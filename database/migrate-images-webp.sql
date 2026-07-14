-- migrate-images-webp.sql - Point existing category/article image paths at .webp assets.
USE earth_odyssey;

UPDATE categories
SET image_path = REPLACE(image_path, '.svg', '.webp')
WHERE image_path LIKE '/assets/img/%' AND image_path LIKE '%.svg';

UPDATE articles
SET thumbnail = REPLACE(thumbnail, '.svg', '.webp')
WHERE thumbnail LIKE '/assets/img/%' AND thumbnail LIKE '%.svg';

-- header-bg moved under hero/ (config default); fix any legacy path if stored
UPDATE categories
SET image_path = '/assets/img/hero/header-bg.webp'
WHERE image_path = '/assets/img/header-bg.svg' OR image_path = '/assets/img/header-bg.webp';

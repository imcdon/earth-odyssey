-- seed.sql - Sample users and published articles for Earth Odyssey.
USE earth_odyssey;

INSERT INTO users (username, password_hash, role) VALUES
('editor', '!', 'editor'),
('author', '!', 'author');

-- Accounts start with login disabled; set passwords with database/set-password.php

INSERT INTO categories (type, slug, name, image_path, sort_order, is_featured) VALUES
('article', 'hiking', 'Hiking', '/assets/img/categories/hiking.webp', 1, 0),
('article', 'camping', 'Camping', '/assets/img/categories/camping.webp', 2, 0),
('article', 'fishing', 'Fishing', '/assets/img/categories/fishing.webp', 3, 0),
('article', 'hunting', 'Hunting', '/assets/img/categories/hunting.webp', 4, 0),
('article', 'weather', 'Weather', '/assets/img/categories/weather.webp', 5, 0),
('gallery', 'landscapes', 'Landscapes', '/assets/img/categories/gallery-landscapes.webp', 1, 0),
('gallery', 'wildlife', 'Wildlife', '/assets/img/categories/gallery-wildlife.webp', 2, 0),
('gallery', 'campsite', 'Campsite', '/assets/img/hero/autumn-camp.webp', 3, 1),
('gallery', 'on-the-water', 'On the Water', '/assets/img/categories/gallery-water.webp', 4, 0);

INSERT INTO articles (slug, title, blurb, body, status, author_id, editor_id, published_at, category_id, thumbnail, is_featured) VALUES
(
    'packing-day-hike',
    'Packing for a Day Hike',
    'What to bring, what to leave behind, and how to keep your load light.',
    'A good day hike starts with a light pack. Bring water, a map, snacks, and layers you can add or remove as the weather shifts.

## Essentials

- Water (more than you think you need)
- Trail map or downloaded offline map
- Sunscreen and a hat
- Small first aid kit

## Nice to have

A light rain shell weighs little and saves the day when the forecast wrong-foots you. Leave the heavy camp gear at home — you are not staying overnight.',
    'published',
    2,
    1,
    NOW(),
    1,
    '/assets/img/hero/day-hike.webp',
    1
),
(
    'first-post',
    'First Post from the Field',
    'A short note on why we started Earth Odyssey and what comes next.',
    'Earth Odyssey began as a simple idea: share honest stories from the trail, the water, and the field.

We are building a home for articles, photo galleries, and free outdoor tools. This is the first post — more guides, galleries, and services are on the way.',
    'published',
    2,
    1,
    NOW(),
    2,
    '/assets/img/hero/autumn-camp.webp',
    0
),
(
    'reading-weather',
    'Reading the Weather Before You Go',
    'Simple checks that help you plan a safer day outdoors.',
    'Before you head out, check the forecast for your exact area — not just the nearest city.

## Quick checks

- Temperature and wind at your elevation
- Chance of storms in the afternoon
- Recent rain (wet trails, rising streams)

If skies look uncertain, have a turn-around time and stick to it. The best trip is the one you come home from.',
    'published',
    2,
    1,
    NOW(),
    5,
    '/assets/img/hero/weather.webp',
    0
);

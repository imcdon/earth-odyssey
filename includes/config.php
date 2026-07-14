<?php
/*
 * config.php - Site-wide settings: name, navigation, and sample data.
 * Edit this file to change values used across every page.
 */
require_once __DIR__ . '/paths.php';

$site_name = 'Earth Odyssey';
$site_tagline = 'Articles, galleries, and outdoor tech';
$company_name = 'Earth Odyssey LLC';
$header_bg_image = '/assets/img/hero/header-bg.webp';

$nav_links = [
    'Home'      => '/',
    'Articles'  => '/articles/',
    'Galleries' => '/galleries/',
    'Services'  => '/services/',
    'About'     => '/about/',
];

$hero_slides = [
    [
        'type'  => 'article',
        'title' => 'Packing for a Day Hike',
        'blurb' => 'What to bring, what to leave behind, and how to keep your load light.',
        'url'   => '/articles/packing-day-hike/',
        'image' => '/assets/img/hero/day-hike.webp',
    ],
    [
        'type'  => 'gallery',
        'title' => 'Campsite',
        'blurb' => 'Photos from the trail, camp, and field — featured in Campsite.',
        'url'   => '/galleries/',
        'image' => '/assets/img/hero/autumn-camp.webp',
    ],
    [
        'type'  => 'article',
        'title' => 'Reading the Weather Before You Go',
        'blurb' => 'Simple checks that help you plan a safer day outdoors.',
        'url'   => '/articles/reading-weather/',
        'image' => '/assets/img/hero/weather.webp',
    ],
    [
        'type'  => 'services',
        'title' => 'Free Outdoor Tech Tools',
        'blurb' => 'Weather, streams, buoys — free tools for your next trip.',
        'url'   => '/services/',
        'image' => '/assets/img/hero/services.webp',
    ],
];

$hero_cta_labels = [
    'article'  => 'Read more',
    'gallery'  => 'View gallery',
    'services' => 'Explore tools',
];

$hero_type_labels = [
    'article'  => 'Article',
    'gallery'  => 'Gallery',
    'services' => 'Services',
];

$features = [
    [
        'title' => 'Articles',
        'description' => 'Stories, guides, and tips from the trail, water, and field.',
        'url'   => '/articles/',
    ],
    [
        'title' => 'Photo Galleries',
        'description' => 'Camping, hunting, fishing, and the places we explore.',
        'url'   => '/galleries/',
    ],
    [
        'title' => 'Services',
        'description' => 'Free outdoor tech tools for weather, streams, and more.',
        'url'   => '/services/',
    ],
];

$latest_articles = [
    [
        'title' => 'Packing for a Day Hike',
        'blurb' => 'What to bring, what to leave behind, and how to keep your load light.',
        'url'   => '/articles/packing-day-hike/',
    ],
    [
        'title' => 'First Post from the Field',
        'blurb' => 'A short note on why we started Earth Odyssey and what comes next.',
        'url'   => '/articles/first-post/',
    ],
    [
        'title' => 'Reading the Weather Before You Go',
        'blurb' => 'Simple checks that help you plan a safer day outdoors.',
        'url'   => '/articles/reading-weather/',
    ],
];

// Panel 1 — hero banner (image + hero copy)
$about_hero_image = '/assets/img/about/hero.webp';
$about_hero_role = 'About';
$about_hero_title = 'Earth Odyssey';
$about_intro = 'Outdoor stories, photography, and free tech tools for campers, hunters, and anglers. Built for anyone who wants more time outside.';
$about_text = $about_intro;

// Panel 2 — editor (portrait + editor copy)
$about_editor = [
    'name'  => 'Ian McDonnell',
    'role'  => 'Editor/Writer',
    'bio'   => 'Always in search of the next adventure. The founder and developer of Earth Odyssey, also the editor and writer. Every year the adventures get bigger, the stories crazier, and my hunts for big fish and game across North America continue.',
    'image' => '/assets/img/about/editor.webp',
];

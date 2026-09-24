<?php
/*
 * config.php - Site-wide settings: name, navigation, themes, and sample data.
 */
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/assets.php';

$site_name = 'Earth Odyssey';
$site_tagline = 'Articles, galleries, and outdoor tech';
$company_name = 'Earth Odyssey LLC';

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

$home_sections = [
    [
        'label' => 'Articles',
        'title' => 'Stories from the trail',
        'blurb' => 'Guides, field notes, and tips for campers, hunters, and anglers.',
        'url'   => '/articles/',
        'cta'   => 'Browse articles',
        'image' => '/assets/img/hero/day-hike.webp',
    ],
    [
        'label' => 'Galleries',
        'title' => 'Places worth seeing',
        'blurb' => 'Landscapes, wildlife, campsites, and water — photos from the field.',
        'url'   => '/galleries/',
        'cta'   => 'Open galleries',
        'image' => '/assets/img/hero/autumn-camp.webp',
    ],
    [
        'label' => 'Tools',
        'title' => 'Outdoor tech, free to use',
        'blurb' => 'Weather, streams, buoys, and more — tools for planning your next trip.',
        'url'   => '/services/',
        'cta'   => 'Explore tools',
        'image' => '/assets/img/hero/services.webp',
    ],
];

$service_tool_groups = [
    [
        'title' => 'Weather & conditions',
        'tools' => [
            [
                'title' => 'Regional forecast',
                'description' => 'Quick look at temperature, wind, and precipitation for your area.',
                'status' => 'soon',
                'url' => null,
            ],
            [
                'title' => 'Stream gauges',
                'description' => 'Check flow levels before you fish or ford a creek.',
                'status' => 'soon',
                'url' => null,
            ],
            [
                'title' => 'Buoy & marine',
                'description' => 'Coastal and lake conditions for boaters and anglers.',
                'status' => 'soon',
                'url' => null,
            ],
        ],
    ],
    [
        'title' => 'Trip helpers',
        'tools' => [
            [
                'title' => 'Pack checklist',
                'description' => 'Build a day-hike or overnight kit without overpacking.',
                'status' => 'soon',
                'url' => null,
            ],
            [
                'title' => 'Sunrise / sunset',
                'description' => 'Daylight windows for hunting, fishing, and trail time.',
                'status' => 'soon',
                'url' => null,
            ],
        ],
    ],
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

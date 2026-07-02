<?php
/*
 * config.php - Site-wide settings: name, navigation, and sample data.
 * Edit this file to change values used across every page.
 */
require_once __DIR__ . '/paths.php';

$site_name = 'Earth Odyssey';
$site_tagline = 'Articles, galleries, and outdoor tech';
$company_name = 'Earth Odyssey LLC';
$header_bg_image = '/assets/img/header-bg.svg';

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
        'image' => '/assets/img/hero/day-hike.svg',
    ],
    [
        'type'  => 'gallery',
        'title' => 'Autumn Camp Gallery',
        'blurb' => 'Photos from a fall camping trip — fire, forest, and early morning mist.',
        'url'   => '/galleries/autumn-camp/',
        'image' => '/assets/img/hero/autumn-camp.svg',
    ],
    [
        'type'  => 'article',
        'title' => 'Reading the Weather Before You Go',
        'blurb' => 'Simple checks that help you plan a safer day outdoors.',
        'url'   => '/articles/reading-weather/',
        'image' => '/assets/img/hero/weather.svg',
    ],
    [
        'type'  => 'services',
        'title' => 'Free Outdoor Tech Tools',
        'blurb' => 'Weather, streams, buoys — free tools for your next trip.',
        'url'   => '/services/',
        'image' => '/assets/img/hero/services.svg',
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

$about_text = 'Earth Odyssey LLC shares outdoor stories, photography, and free tech tools for campers, hunters, and anglers. We build simple resources that help you plan trips, learn from the field, and enjoy time outside.';

$about_hero_image = '/assets/img/about/hero.svg';
$about_hero_title = 'About Earth Odyssey';
$about_intro = $about_text;

$about_pillars = [
    [
        'title'       => 'Economy of Words',
        'description' => 'Think about the before time. People were limited by how much paint they could put on a wall, or how much carvings they can make in stone, then ink and paper, and now we\'re basically unlimited on how many words we can write. We\'ll do our best to do as much as we can with as little as we can.',
        'image'       => '/assets/img/about/pillar-stories.svg',
    ],
    [
        'title'       => 'No Logins or Subscriptions',
        'description' => 'No logins or subscriptions, no B.S. Everyone gets access to the content, free for everyone.',
        'image'       => '/assets/img/about/pillar-photography.svg',
    ],
    [
        'title'       => 'We Do Not Sell Information',
        'description' => 'We are against the practice of selling user information. Any metrics (if collected at all) are anonymized and used for our own operations. Metrics that we collect are page views, likes, dislikes, and information from article submissions. Nothing else.',
        'image'       => '/assets/img/about/pillar-knowledge.svg',
    ],
    [
        'title'       => 'Free Tools & Services',
        'description' => 'Weather, streams, and other outdoor tech — simple tools that cost nothing and respect your time.',
        'image'       => '/assets/img/about/pillar-tools.svg',
    ],
];

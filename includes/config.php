<?php
/*
 * config.php - Site-wide settings: name, navigation, themes, and sample data.
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/assets.php';

$site_name = 'Earth Odyssey';
$site_tagline = 'Outdoor tools, photo galleries, and field stories';
$company_name = 'Earth Odyssey LLC';

$nav_links = [
    'Galleries' => '/galleries/',
    'Articles'  => '/articles/',
    'Books'     => '/books/',
    'Tools'     => '/services/',
    'Advocacy'  => '/advocacy/',
    'About'     => '/about/',
];

$advocacy_groups = [];

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

/* Home destination bands are built in index.php (galleries only). */

$service_tool_groups = [
    [
        'title' => 'Water',
        'tools' => [
            [
                'title' => 'Stream gauges',
                'description' => 'Look up live USGS flow, gauge height, and water temperature before you fish or ford a creek. Search a river, creek, or town, pick a state, and compare this week, this month, or this year with the same stretch of water last year.',
                'image' => '/assets/img/galleries/alaska/img_3440.webp',
                'status' => 'beta',
                'url' => '/services/rivers/',
            ],
            [
                'title' => 'Fishing reports',
                'description' => 'Write the trip while it is still fresh. Choose the gauges you fished, set the date and time, and flow, height, and the weather from that stretch are filled in when you save.',
                'image' => '/assets/img/categories/fish.webp',
                'status' => 'beta',
                'url' => '/services/rivers/reports.php',
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

// Panel 2 — editor (portrait + editor copy)
$about_editor = [
    'name'  => 'Ian McDonnell',
    'role'  => 'Editor/Writer',
    'bio'   => [
        'Since I was very young, I\'ve been obsessed with the outdoors. As a kid I would flip over rocks and logs in my backyard looking for any creepy or crawly thing I could find. As I got older I turned into a fishing freak, and now a calmer outdoor enthusiast with a passion for showcasing the outdoors, experiencing every new place I can, and advocating for it\'s protection.',
        'I started Earth Odyssey with the goal of providing a platform and tools for other outdoor enthusiasts. In doing this I had a few principles; one, that it would be free, and two, it would advocate for wildlife and the world at large. Earth Odyssey is also a portfolio piece for my web design and coding skills, as well as my writing and photography.',
        'There\'s a few primary features you\'ll find on earth-odyssey.com, the first one is the articles tab. This is a place where myself and writers that submit articles via the portal can have their articles published without the pressure of a company editor or pernicious content companies. All of it is free and not behind a paywall or sign-in wall.',
        'Another item is the Galleries tab. This is where I display collections of my own photos. A few collections have photos from my iPhone, which isn\'t as appealing as my collections from my Nikon D600 with a standard kit lense. It\'s just a place for me and others to publish and for users to view high-definition pictures in a viewer friendly format.',
        'Another cool tab is the books tab. This tab uses open source libraries and APIs to compile public domain works about fishing, hunting, or anything outdoors. Peruse ancient titles like the Compleat Angler and more.',
        'The last and nerdiest item is the tools tab. These are custom coded tools that use APIs and php to make tools that outdoor enthusiasts can use to plan and learn. The streamguage tool uses USGS stream monitoring data with a modern interface. The fishing reports tool uses USGS data and weather data to autofill certain information about a day on the water.',
        'The most impactful item is the advocacy tab. Every year, habitats are threatened and more species are on the verge of extinction. In addition, more and more people are displaced and killed by polution and war every year. Even if I can not make a difference myself, I can help by encouraging users of Earth Odyssey to educate themselves on advocacy for habitats, for species, and for people.',
        'Please, If you have any questions, bugs, concerns, or would like to contribute articles, photos, or technology, hit up the contact page.',
        'Thank you!',
    ],
    'image' => '/assets/img/about/editor.webp',
];

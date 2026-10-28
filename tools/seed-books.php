<?php
/*
 * seed-books.php - Insert featured public-domain titles and download Gutenberg HTML/covers.
 * CLI: C:\xampp\php\php.exe tools\seed-books.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';
require __DIR__ . '/../includes/books.php';

$featured = [
    [
        'source'      => BOOK_SOURCE_GUTENBERG,
        'source_id'   => '683',
        'slug'        => 'compleat-angler',
        'title'       => 'The Compleat Angler',
        'author'      => 'Izaak Walton',
        'year'        => 1653,
        'subjects'    => 'fishing',
        'blurb'       => 'Walton’s quiet dialogue on rivers, flies, and the art of angling — first printed in 1653.',
        'source_url'  => 'https://www.gutenberg.org/ebooks/683',
        'is_featured' => 1,
        'sort_order'  => 10,
    ],
    [
        'source'      => BOOK_SOURCE_GUTENBERG,
        'source_id'   => '34607',
        'slug'        => 'woodcraft-and-camping',
        'title'       => 'Woodcraft and Camping',
        'author'      => 'George Washington Sears (Nessmuk)',
        'year'        => 1920,
        'subjects'    => 'camping',
        'blurb'       => 'Nessmuk’s lean-camping advice: a light kit, a small fire, and living well in the woods.',
        'source_url'  => 'https://www.gutenberg.org/ebooks/34607',
        'is_featured' => 1,
        'sort_order'  => 20,
    ],
    [
        'source'      => BOOK_SOURCE_GUTENBERG,
        'source_id'   => '44215',
        'slug'        => 'camp-lore-and-woodcraft',
        'title'       => 'The Book of Camp-Lore and Woodcraft',
        'author'      => 'Daniel Carter Beard',
        'year'        => 1920,
        'subjects'    => 'camping',
        'blurb'       => 'Dan Beard’s camp skills — fire, shelter, knots, and trail craft for the outdoor camp.',
        'source_url'  => 'https://www.gutenberg.org/ebooks/44215',
        'is_featured' => 1,
        'sort_order'  => 30,
    ],
    [
        'source'      => BOOK_SOURCE_GUTENBERG,
        'source_id'   => '32540',
        'slug'        => 'first-summer-in-the-sierra',
        'title'       => 'My First Summer in the Sierra',
        'author'      => 'John Muir',
        'year'        => 1911,
        'subjects'    => 'travel,nature',
        'blurb'       => 'Muir’s 1869 journal of the High Sierra: flocks, granite, light, and the range of light.',
        'source_url'  => 'https://www.gutenberg.org/ebooks/32540',
        'is_featured' => 1,
        'sort_order'  => 40,
    ],
    [
        'source'      => BOOK_SOURCE_GUTENBERG,
        'source_id'   => '42500',
        'slug'        => 'the-maine-woods',
        'title'       => 'The Maine Woods',
        'author'      => 'Henry David Thoreau',
        'year'        => 1864,
        'subjects'    => 'travel,nature',
        'blurb'       => 'Canoe trips into the Maine wilderness, including the climb toward Ktaadn.',
        'source_url'  => 'https://www.gutenberg.org/ebooks/42500',
        'is_featured' => 1,
        'sort_order'  => 50,
    ],
    [
        'source'      => BOOK_SOURCE_GUTENBERG,
        'source_id'   => '205',
        'slug'        => 'walden',
        'title'       => 'Walden',
        'author'      => 'Henry David Thoreau',
        'year'        => 1854,
        'subjects'    => 'nature',
        'blurb'       => 'Two years beside a pond, written as a field note on how to live.',
        'source_url'  => 'https://www.gutenberg.org/ebooks/205',
        'is_featured' => 1,
        'sort_order'  => 60,
    ],
    [
        'source'      => BOOK_SOURCE_GUTENBERG,
        'source_id'   => '34110',
        'slug'        => 'deadfalls-and-snares',
        'title'       => 'Deadfalls and Snares',
        'author'      => 'A. R. Harding',
        'year'        => 1907,
        'subjects'    => 'hunting',
        'blurb'       => 'Old trap-line methods from Harding’s fur-trade how-to series — deadfalls, snares, and sets.',
        'source_url'  => 'https://www.gutenberg.org/ebooks/34110',
        'is_featured' => 1,
        'sort_order'  => 70,
    ],
    [
        'source'      => BOOK_SOURCE_GUTENBERG,
        'source_id'   => '31709',
        'slug'        => 'our-southern-highlanders',
        'title'       => 'Our Southern Highlanders',
        'author'      => 'Horace Kephart',
        'year'        => 1913,
        'subjects'    => 'travel,camping',
        'blurb'       => 'Kephart among the people of the Great Smokies: camps, hunting, and mountain life.',
        'source_url'  => 'https://www.gutenberg.org/ebooks/31709',
        'is_featured' => 1,
        'sort_order'  => 80,
    ],
];

foreach ($featured as $i => $book) {
    fwrite(STDOUT, sprintf("[%d/%d] %s … ", $i + 1, count($featured), $book['title']));
    $book += ['cover_path' => '', 'html_path' => null, 'epub_path' => null];
    $book = book_download_featured($book);
    book_upsert($book);
    $bits = [];
    if (!empty($book['cover_path']) && is_file(dirname(__DIR__) . '/' . $book['cover_path'])) {
        $bits[] = 'cover';
    }
    if (!empty($book['html_path']) && is_file(dirname(__DIR__) . '/' . $book['html_path'])) {
        $bits[] = 'html';
    }
    fwrite(STDOUT, ($bits ? implode('+', $bits) : 'metadata only') . PHP_EOL);
    if ($i < count($featured) - 1) {
        sleep(1);
    }
}

fwrite(STDOUT, "Seeded " . count($featured) . " featured titles.\n");

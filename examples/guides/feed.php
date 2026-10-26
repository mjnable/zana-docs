<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Translation\LocalizedValue;

// The published posts, as a repository returns them.
$posts = [
    ['slug' => new LocalizedValue(['en' => 'launch', 'fr' => 'lancement']), 'title' => new LocalizedValue(['en' => 'Launch day', 'fr' => 'Jour de lancement']), 'published' => '2026-10-20T09:00:00Z'],
    ['slug' => new LocalizedValue(['en' => 'hiring']), 'title' => new LocalizedValue(['en' => 'We are hiring']), 'published' => '2026-10-24T09:00:00Z'],
];

// region: feed
// One feed per locale, e.g. GET /fr/blog/feed.xml with Content-Type: application/atom+xml.
$locale = 'fr';
$origin = 'https://acme.example';
$e = static fn (string $text): string => htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');

$entries = '';
foreach ($posts as $post) {
    $title = $post['title']->in($locale);
    if ($title === null) {
        continue;   // not translated: absent from this locale's feed, never a silent fallback
    }
    $url = "{$origin}/{$locale}/blog/" . $post['slug']->in($locale);
    $entries .= "  <entry><title>{$e($title)}</title><link href=\"{$e($url)}\"/><id>{$e($url)}</id><updated>{$post['published']}</updated></entry>\n";
}

echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<feed xmlns=\"http://www.w3.org/2005/Atom\" xml:lang=\"{$locale}\">\n",
    "  <title>Acme — Blog</title>\n  <link rel=\"self\" href=\"{$origin}/{$locale}/blog/feed.xml\"/>\n  <id>{$origin}/{$locale}/blog</id>\n",
    "  <updated>2026-10-24T09:00:00Z</updated>\n", $entries, "</feed>\n";
// endregion: feed

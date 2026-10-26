<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Translation\LocalizedValue;

// region: content
// Store one column per field holding JSON ({"en": "...", "fr": "..."}), or one row per locale.
$row = ['title' => '{"en":"Our services","fr":"Nos services"}', 'body' => '{"en":"We build websites."}'];

$title = new LocalizedValue(json_decode($row['title'], true));
$body = new LocalizedValue(json_decode($row['body'], true));

$locales = ['en', 'fr'];
echo 'fr title: ', $title->in('fr'), "\n";
echo 'fr body: ', var_export($body->in('fr'), true), "\n";
echo 'fr body, falling back: ', $body->in('fr', fallback: 'en'), "\n";
echo 'Missing for editors: ', json_encode(['title' => $title->missing($locales), 'body' => $body->missing($locales)]), "\n";

// Publish a page in a locale only when every field is translated, and link only those locales.
$published = array_values(array_filter($locales, static fn (string $l): bool => $title->has($l) && $body->has($l)));
echo 'Published in: ', implode(', ', $published), "\n";

$title = $title->with('fr', 'Nos prestations');   // an editor's correction
echo 'Saved: ', json_encode($title, JSON_UNESCAPED_UNICODE), "\n";
// endregion: content

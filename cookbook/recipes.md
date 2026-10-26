# Recipes for content sites

Short, tested solutions for the things a company website or blog needs
once its pages exist. Each one builds on the
[company website](website.md) chapter.

## A sitemap

Search engines find every page, in every language, from `/sitemap.xml`.
Mark the routes to list with metadata (2.2), so the sitemap stays in step
with the routes:

<!-- example: examples/guides/sitemap.php#routes -->
```php
$router = new Router();
$router->localized(['en' => '/', 'fr' => '/'], static fn (): string => 'home')->name('home')->meta(['sitemap' => true, 'priority' => 1.0]);
$router->localized(['en' => '/about', 'fr' => '/a-propos'], static fn (): string => 'about')->name('about')->meta(['sitemap' => true]);
$router->localized(['en' => '/contact', 'fr' => '/contact'], static fn (): string => 'contact')->name('contact')->meta(['sitemap' => true]);
$router->get('/admin', static fn (): string => 'admin');   // no metadata: never listed
```

With attributes, the same is `#[Route('/about', name: 'about', meta: ['sitemap' => true])]`.
The controller walks the routes and lists each page once per locale, with
its translations as `hreflang` alternates:

<!-- example: examples/guides/sitemap.php#sitemap -->
```php
// A SitemapController would return this with Content-Type: application/xml.
$origin = 'https://acme.example';   // app.url
$urls = new UrlGenerator($router);

$seen = [];
$xml = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'];
foreach ($router->routes() as $route) {
    /** @var Route $route */
    $name = (string) $route->getName();
    $base = substr($name, 0, (int) strrpos($name, '.'));   // "about.fr" belongs to "about"
    if (($route->getMeta()['sitemap'] ?? false) !== true || isset($seen[$base])) {
        continue;
    }
    $seen[$base] = true;
    $alternates = $urls->alternates($base);
    foreach ($alternates as $path) {
        $xml[] = '  <url><loc>' . $origin . $path . '</loc>';
        foreach ($alternates as $locale => $alternate) {
            $xml[] = "    <xhtml:link rel=\"alternate\" hreflang=\"{$locale}\" href=\"{$origin}{$alternate}\"/>";
        }
        $xml[] = '  </url>';
    }
}
$xml[] = '</urlset>';
echo implode("\n", $xml), "\n";
```

<!-- example: examples/guides/sitemap.out -->
```text
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <url><loc>https://acme.example/en</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://acme.example/en"/>
    <xhtml:link rel="alternate" hreflang="fr" href="https://acme.example/fr"/>
  </url>
  <url><loc>https://acme.example/fr</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://acme.example/en"/>
    <xhtml:link rel="alternate" hreflang="fr" href="https://acme.example/fr"/>
  </url>
  <url><loc>https://acme.example/en/about</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://acme.example/en/about"/>
    <xhtml:link rel="alternate" hreflang="fr" href="https://acme.example/fr/a-propos"/>
  </url>
  <url><loc>https://acme.example/fr/a-propos</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://acme.example/en/about"/>
    <xhtml:link rel="alternate" hreflang="fr" href="https://acme.example/fr/a-propos"/>
  </url>
  <url><loc>https://acme.example/en/contact</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://acme.example/en/contact"/>
    <xhtml:link rel="alternate" hreflang="fr" href="https://acme.example/fr/contact"/>
  </url>
  <url><loc>https://acme.example/fr/contact</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://acme.example/en/contact"/>
    <xhtml:link rel="alternate" hreflang="fr" href="https://acme.example/fr/contact"/>
  </url>
</urlset>
```

Use `app.url` as the origin rather than the request's host, which a
visitor controls. For content from the database (posts, products), add
their URLs in the same loop. Cache the response with `#[Cache(maxAge: 3600)]`
(see [HTTP](../guides/http.md#new-in-22-content-sites)), and list the
sitemap in `robots.txt`:

```
Sitemap: https://acme.example/sitemap.xml
```

## An RSS or Atom feed

One feed per locale, listing only the posts translated into that locale:

<!-- example: examples/guides/feed.php#feed -->
```php
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
```

<!-- example: examples/guides/feed.out -->
```text
<?xml version="1.0" encoding="utf-8"?>
<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="fr">
  <title>Acme — Blog</title>
  <link rel="self" href="https://acme.example/fr/blog/feed.xml"/>
  <id>https://acme.example/fr/blog</id>
  <updated>2026-10-24T09:00:00Z</updated>
  <entry><title>Jour de lancement</title><link href="https://acme.example/fr/blog/lancement"/><id>https://acme.example/fr/blog/lancement</id><updated>2026-10-20T09:00:00Z</updated></entry>
</feed>
```

Escape every value with `ENT_XML1`, and link the feed from the layout's
`<head>`:

```html
<link rel="alternate" type="application/atom+xml" href="/fr/blog/feed.xml" title="Acme — Blog">
```

## Translated content

Templates and messages are translated with catalogs
([Translation](../guides/translation.md)). Content that editors write
(pages, posts, product descriptions) is translated in the database.
`LocalizedValue` (2.2) holds one value per locale:

<!-- example: examples/guides/translated-content.php#content -->
```php
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
```

<!-- example: examples/guides/translated-content.out -->
```text
fr title: Nos services
fr body: NULL
fr body, falling back: We build websites.
Missing for editors: {"title":[],"body":["fr"]}
Published in: en
Saved: {"en":"Our services","fr":"Nos prestations"}
```

Rules worth knowing:

- **No silent fallback.** A page shown in French with an English body is
  worse than no French page. Publish per locale, skip untranslated items in
  lists and feeds, and answer 404 for a missing translation. Use
  `fallback:` only where mixing languages is acceptable, such as a
  product's technical name.
- **Show editors what is missing.** `missing($locales)` lists the locales
  without a value, for a badge in the back office.
- **Slugs are content too.** Translate them, and look a record up by the
  slug in the request's locale.
- **Keep a history per locale** with [Audit](../guides/audit.md).
- Media alt texts follow the same rule (see [Media](../guides/media.md)).

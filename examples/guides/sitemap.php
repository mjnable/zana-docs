<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Routing\Route;
use Zana\Routing\Router;
use Zana\Routing\UrlGenerator;

// region: routes
$router = new Router();
$router->localized(['en' => '/', 'fr' => '/'], static fn (): string => 'home')->name('home')->meta(['sitemap' => true, 'priority' => 1.0]);
$router->localized(['en' => '/about', 'fr' => '/a-propos'], static fn (): string => 'about')->name('about')->meta(['sitemap' => true]);
$router->localized(['en' => '/contact', 'fr' => '/contact'], static fn (): string => 'contact')->name('contact')->meta(['sitemap' => true]);
$router->get('/admin', static fn (): string => 'admin');   // no metadata: never listed
// endregion: routes

// region: sitemap
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
// endregion: sitemap

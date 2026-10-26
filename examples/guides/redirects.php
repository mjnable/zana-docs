<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Nyholm\Psr7\ServerRequest;
use Zana\Core\Application;
use Zana\Http\HttpKernel;
use Zana\Http\HttpServiceProvider;
use Zana\Routing\Router;
use Zana\Routing\RoutingServiceProvider;

$container = Application::configure(sys_get_temp_dir() . '/zana-docs-redirects')
    ->withEnvironment('testing')
    ->withProviders(HttpServiceProvider::class, RoutingServiceProvider::class)
    ->build()
    ->container();
$router = $container->get(Router::class);
assert($router instanceof Router);
$router->localized(['en' => '/about', 'fr' => '/a-propos'], static fn (): string => 'about')->name('about');
$router->get('/blog/{slug}', static fn (string $slug): string => $slug)->name('blog.show');

// region: redirects
// The old site's URLs: permanent (301) redirects to the new routes.
$router->redirect('/qui-sommes-nous', 'about', parameters: ['_locale' => 'fr']);
$router->redirect('/news/{slug}', 'blog.show');   // the slug carries over

// A staff shortcut: temporary, never cached, indexed or leaking the referrer.
$router->redirect('/webmail', 'https://mail.acme.example:2096', 302, [
    'Cache-Control' => 'no-store',
    'X-Robots-Tag' => 'noindex',
    'Referrer-Policy' => 'no-referrer',
]);
// endregion: redirects

$kernel = $container->get(HttpKernel::class);
assert($kernel instanceof HttpKernel);
foreach (['/qui-sommes-nous', '/news/launch-day', '/webmail'] as $path) {
    $response = $kernel->handle(new ServerRequest('GET', $path));
    printf("%-17s %d %s%s\n", $path, $response->getStatusCode(), $response->getHeaderLine('Location'), $response->hasHeader('X-Robots-Tag') ? ' (noindex, no-store)' : '');
}

// Redirect routes have no closure, so the route cache keeps them.
$cached = new Router();
$cached->redirect('/qui-sommes-nous', 'about', parameters: ['_locale' => 'fr']);
echo count(Router::fromExport($cached->export())->routes()), " redirect restored from the route cache\n";

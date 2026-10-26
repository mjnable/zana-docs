# Routing

`mj-zana/routing` maps HTTP methods and paths to handlers: closures,
invokable classes, or controller methods. It becomes the HTTP kernel's
terminal handler and calls handlers through the request's container scope.

```
composer require mj-zana/routing
```

## Controllers with attributes

<!-- example: examples/guides/routing.php#attributes -->
```php
#[Route('/api/patients')]
final class PatientController
{
    /**
     * @return array<string, mixed>
     */
    #[Route('', methods: ['GET'], name: 'patients.index')]
    public function index(ServerRequestInterface $request): array
    {
        return ['page' => (int) ($request->getQueryParams()['page'] ?? 1)];
    }

    /**
     * @return array<string, mixed>
     */
    #[Route('/{id}', methods: ['GET'], name: 'patients.show')]
    public function show(int $id): array   // route values convert to the declared type
    {
        return ['id' => $id];
    }
}
```

## Defining routes

Modules add routes in `boot()`:

<!-- example: examples/guides/routing.php#routes -->
```php
$router = $container->get(Router::class);
$router->loadAttributes(PatientController::class);   // controllers must be registered services

$router->get('/health', static fn (): string => 'ok')->name('health');
$router->group('/api/reports', static function (Router $r): void {
    $r->get('/{year}', static fn (int $year): array => ['year' => $year])->where('year', '\d{4}');
});
```

## Dispatching and URLs

<!-- example: examples/guides/routing.php#dispatch -->
```php
$kernel = $container->get(HttpKernel::class);
foreach (['/api/patients?page=2', '/api/patients/7', '/api/patients/abc', '/api/reports/2026', '/health'] as $path) {
    $response = $kernel->handle(new ServerRequest('GET', $path, ['Accept' => 'application/json']));
    echo rtrim(str_pad($path, 22) . $response->getStatusCode() . ' ' . ($response->getStatusCode() < 400 ? $response->getBody() : '')), "\n";
}

$urls = $container->get(UrlGenerator::class);
echo $urls->generate('patients.show', ['id' => 42], ['tab' => 'bmi']), "\n";
```

<!-- example: examples/guides/routing.out -->
```text
/api/patients?page=2  200 {"page":2}
/api/patients/7       200 {"id":7}
/api/patients/abc     404
/api/reports/2026     200 {"year":2026}
/health               200 ok
/api/patients/42?tab=bmi
```

`/api/patients/abc` is a 404 because `abc` is not an `int`. A route that
matches the path but not the method answers 405, with an `Allow` header.

## What a handler receives and returns

- **Parameters:**
  - route values by name, converted to `int`, `float` or `bool` when
    declared so;
  - `ServerRequestInterface` for the request (with route values also as
    attributes);
  - any other service, injected from the request scope.
- **Return values:**

  | Returned | Response |
  |---|---|
  | `ResponseInterface` | used as is |
  | `null` | 204 |
  | a string | HTML |
  | an array, a scalar or a `JsonSerializable` | JSON |
  | another object | JSON of its public properties |

## Rules worth knowing

- **Controllers and invokable handlers must be registered**, usually as
  `transient`, so they get their own dependencies.
- **Middleware:** `->middleware(...)` adds middleware to one route, and
  `group($prefix, $routes, [$middleware])` to a whole group. The request
  attribute `_handler` tells middleware which handler matched, which is how
  security's authorization middleware reads attributes.
- **Route names:** must be unique. `UrlGenerator::generate()` fills
  parameters and adds a query string.

## New in 2.1: localised routes

```php
$router->localized(['en' => '/about', 'fr' => '/a-propos'], [PageController::class, 'about'])->name('about');
```

- **Variants:** this registers `/en/about` as `about.en` and
  `/fr/a-propos` as `about.fr`, each with the `_locale` default, which
  becomes a request attribute.
- **Generating URLs:**
  - `generate('about')` uses the current request's locale;
  - `generate('about', ['_locale' => 'fr'])` asks for one;
  - `alternates('about')` lists every language, for `hreflang` links and
    language switchers.
- **URL defaults:** while a route runs, its defaults and parameters fill
  the missing parameters of generated URLs, so a page can link to its own
  variants.
- **Result converters:** services tagged `routing.result_converters` turn
  custom results into responses. The view package uses them for `View`.

## New in 2.2

- **Route metadata.** `->meta(['sitemap' => true])`, or
  `#[Route(..., meta: [...])]`, attaches data to a route for your own code,
  such as a [sitemap](../cookbook/recipes.md#a-sitemap). `getMeta()` reads
  it; it is kept in the route cache.
- **Sub-folders.** Generated URLs start with the application's base path
  (see [HTTP](http.md#a-sub-folder)).

Reference: the [routing README](https://github.com/mjnable/zana-routing).

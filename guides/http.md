# HTTP

`mj-zana/http` handles PSR-7 requests through a PSR-15 middleware pipeline.
Each request runs in its own container scope, and errors become proper
responses. It uses `nyholm/psr7` behind the PSR interfaces.

```
composer require mj-zana/http
```

## The kernel

`HttpKernel` runs each request through three stages:

1. the error middleware;
2. your middleware, from `http.middleware`;
3. the terminal handler, the service `http.handler`.

[Routing](routing.md) provides the handler in most applications. Here it's a
plain handler, to show the kernel on its own:

<!-- example: examples/guides/http.php#handler -->
```php
final readonly class HelloHandler implements RequestHandlerInterface
{
    public function __construct(private ResponseFactory $responses)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return match ($request->getUri()->getPath()) {
            '/' => $this->responses->json(['hello' => 'world']),
            default => throw new NotFoundHttpException('Nothing here.'),
        };
    }
}
```

Middleware is plain PSR-15:

<!-- example: examples/guides/http.php#middleware -->
```php
final readonly class PoweredBy implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)->withHeader('X-Powered-By', 'Zana');
    }
}
```

<!-- example: examples/guides/http.php#config -->
```php
// config/http.php
file_put_contents("{$base}/config/http.php", '<?php return ["middleware" => [PoweredBy::class]];');

$app = Application::configure($base)
    ->withEnvironment('production')
    ->withProviders(HttpServiceProvider::class)
    ->withServices(static fn (ServiceCollectionInterface $s) => $s
        ->replace(HttpKernel::HANDLER, HelloHandler::class)   // routing normally provides the handler
        ->scoped(PoweredBy::class))
    ->build();
$kernel = $app->container()->get(HttpKernel::class);
```

## Responses and errors

<!-- example: examples/guides/http.php#requests -->
```php
$ok = $kernel->handle(new ServerRequest('GET', '/', ['X-Correlation-Id' => 'trace-1']));
echo $ok->getStatusCode(), ' ', $ok->getBody(), "\n";
echo 'X-Powered-By: ', $ok->getHeaderLine('X-Powered-By'), "\n";
echo 'X-Correlation-Id: ', $ok->getHeaderLine('X-Correlation-Id'), "\n";

$missing = $kernel->handle(new ServerRequest('GET', '/nope', ['Accept' => 'application/json']));
echo $missing->getStatusCode(), ' ', $missing->getHeaderLine('Content-Type'), "\n";
echo $missing->getBody(), "\n";
```

<!-- example: examples/guides/http.out -->
```text
200 {"hello":"world"}
X-Powered-By: Zana
X-Correlation-Id: trace-1
404 application/problem+json; charset=utf-8
{"type":"about:blank","title":"Not Found","status":404,"detail":"Nothing here.","instance":"/nope"}
```

## Rules worth knowing

- **Errors:** throw the HTTP exceptions (`BadRequestHttpException`,
  `NotFoundHttpException`, `UnprocessableEntityHttpException` with field
  errors, and others).
  - **Format:** clients that accept JSON get an RFC 9457 problem document;
    browsers get an HTML page.
  - **Server errors:** any other exception becomes a 500 and is logged.
  - **Debug mode:** outside production, responses include the exception
    and its trace.
- **Middleware sees exceptions, not error responses.** The error
  middleware runs outside yours, so an exception passes through your
  middleware before it becomes a response. A logging middleware that wants
  the final status must catch it, e.g. `HttpException::$status`.
- **Request scope:** services registered as `scoped` live for one request.
  `RequestContext` holds the current request, and the request carries
  `correlation_id` and `request_id` attributes.
- **Bodies are not parsed automatically.** Read JSON with
  `json_decode((string) $request->getBody(), true)`. Form posts arrive in
  `getParsedBody()`.
- **Serving:** `HttpKernel::serve($app)` builds the request from PHP's
  globals, emits the response, and terminates the application.
- **Not included in 2.0:** CSRF protection, CORS and security headers.
  Add them as middleware ([security](security.md)).

## New in 2.1

- **Security headers.** Add `SecurityHeadersMiddleware` first in
  `http.middleware`. It sends a Content-Security-Policy, HSTS on HTTPS,
  `nosniff`, a referrer policy, frame protection and a permissions
  policy, without replacing headers your handlers set. Configure it in
  `http.security_headers`:
  - `{nonce}` in a CSP source becomes a fresh nonce per request; templates
    print it as `csp_nonce`;
  - `null` removes a default header or directive.
- **Error pages.** Register an `ErrorRendererInterface` to draw the HTML
  error pages. The [view package](view.md) provides one that uses
  templates. Server errors in debug mode keep the built-in page with the
  trace.
- **No version banner.** PHP's `X-Powered-By` header is removed unless
  `http.expose_php` is true.
- **Safe startup.** `HttpKernel::run($builder)` builds and serves. If the
  build fails, visitors get a 503 with `public/503.html`, and the
  exception goes to the error log only.

## New in 2.2: content sites

### Behind a proxy

Behind a CDN or a load balancer, every request comes from the proxy. List
the proxies in `http.trusted_proxies` (addresses and CIDR ranges) and put
`TrustedProxiesMiddleware` first in `http.middleware`:

```php
'trusted_proxies' => ['10.0.0.0/8', '173.245.48.0/20'],
```

`X-Forwarded-For` (or `Forwarded`), `-Proto` and `-Port` are then read from
those proxies only, since anyone can send them.
`RequestContext::clientIp()` gives the visitor's address, and
`isSecure()` and generated URLs see HTTPS.

### A sub-folder

An application served from `http://localhost/shop` works without a
rewrite: the kernel removes the base path before routing and the URL
generator and `asset()` add it back. It is found from `SCRIPT_NAME`;
set `http.base_path` to override it. `RequestContext::basePath()` gives it.

### Forms

`FormInput::from($request)` reads a submitted form: strings are trimmed
and blank fields are absent, so validation sees `null` rather than `""`.
`string()`, `optional()`, `list()`, `bool()`, `has()`, `only()` and `all()`.

### HTTP caching

`#[Cache(maxAge: 600, sharedMaxAge: 3600)]` on a handler becomes its
`Cache-Control`, with `CacheHeadersMiddleware` as route middleware. It
also adds a weak `ETag` and answers a matching `If-None-Match` with 304.
`public` and `immutable` are options too. A response that sets a cookie is
never made cacheable.

### Page cache

`PageCacheMiddleware` serves whole public pages from the PSR-16 cache. A
page is stored only for a GET or HEAD without `Authorization` or a session
cookie (`http.page_cache.private_cookies`), and when the response is a 200
that sets no cookie and is not private. Responses carry `X-Page-Cache: HIT`
or `MISS`.

Tag pages, and invalidate the tags when the content changes:

```php
return $view->withHeader(PageCache::TAGS_HEADER, "post:{$id}, nav");   // removed before sending

$pageCache->invalidate("post:{$id}");   // after saving the post
```

Invalidation never scans the cache. `http.page_cache.ttl` (3,600 seconds)
bounds an entry's life, and `http.page_cache.shared_max_age` also sends
`s-maxage` for a CDN.

### Errors

`TooManyRequestsHttpException` (429, with `Retry-After`) and
`ServiceUnavailableHttpException` (503) join the HTTP exceptions.

Reference: the [http README](https://github.com/mjnable/zana-http).

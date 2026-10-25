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

Reference: the [http README](https://github.com/mjnable/zana-http).

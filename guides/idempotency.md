# Idempotency

`mj-zana/idempotency` (2.14) lets a client retry a request that must not
run twice. The first request with an `Idempotency-Key` header runs and its
response is stored; a retry gets that response again.

```
composer require mj-zana/idempotency
```

A buyer on a slow mobile connection taps "pay", and the answer never
arrives. The app retries. Without a key, the retry is a second payment.
With one, the server recognises the retry.

## Marking a handler

Mark the handler with `#[Idempotent]`, and run `IdempotencyMiddleware` on
its route:

<!-- example: examples/guides/idempotency.php#handler -->
```php
final class PayOrderAction
{
    public int $payments = 0;

    // #[Route('/api/orders/{id}/pay', methods: ['POST'], middleware: [IdempotencyMiddleware::class])]
    #[Idempotent(required: true)]   // a request without an Idempotency-Key gets 400
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->payments++;   // ... charge the card, create the tickets ...

        return new Response(201, ['Content-Type' => 'application/json'], json_encode(['payment' => $this->payments]));
    }
}
```

- **`required: true`** refuses requests without a key. By default, they run
  unprotected.
- **`ttl`** keeps the key for that many seconds instead of
  `idempotency.ttl` (a day).
- **The middleware** only acts on `#[Idempotent]` handlers, so it can run
  on a whole route group.

## Retries

The client generates a new key (a UUID) for each user action, and sends the
same key with each retry of that action:

<!-- example: examples/guides/idempotency.php#retry -->
```php
// The buyer taps "pay"; the connection drops before the answer arrives, so the app sends it again
// with the same key. Then a bug in the app reuses the key for another amount.
$pay = static function (string $key, string $body) use ($middleware, $router): string {
    $request = (new ServerRequest('POST', '/api/orders/7/pay', ['Idempotency-Key' => $key], $body))
        ->withAttribute('_handler', PayOrderAction::class);   // set by the router
    try {
        $response = $middleware->process($request, $router);

        return $response->getStatusCode() . ' ' . $response->getBody() . ($response->hasHeader('Idempotent-Replayed') ? ' (replayed)' : '');
    } catch (HttpException $e) {
        return "{$e->status} {$e->getMessage()}";
    }
};

echo 'First try:  ', $pay('0b6c6f7e-pay-7', '{"amount":25}'), "\n";
echo 'Retry:      ', $pay('0b6c6f7e-pay-7', '{"amount":25}'), "\n";
echo 'Other body: ', $pay('0b6c6f7e-pay-7', '{"amount":40}'), "\n";
echo 'Payments taken: ', $action->payments, "\n";
```

The retry gets the first response, and the payment is taken once:

<!-- example: examples/guides/idempotency.out -->
```text
First try:  201 {"payment":1}
Retry:      201 {"payment":1} (replayed)
Other body: 422 The Idempotency-Key was already used with a different request.
Payments taken: 1
```

What a client gets:

| Request | Response |
|---|---|
| A new key | The handler runs; its response is stored |
| The same key and the same request | The stored response, with `Idempotent-Replayed: true` |
| The same key while the first request still runs | 409, with `Retry-After: 1` |
| The same key with another method, path, query or body | 422 |
| A key that is not 1 to 255 visible ASCII characters | 400 |

- **Atomic:** a key is claimed with one database insert, so of two
  simultaneous requests exactly one runs, even on several servers.
- **Failures free the key:** a response of 500 or more, or an exception,
  is not stored, so a retry can succeed. A 4xx response, such as a
  declined card, is an answer and is replayed.
- **Stopped requests:** a request whose process stops holds its key for
  `idempotency.lock_seconds`; a retry after that runs again.
- **Per user:** with [security](security.md), keys belong to the signed-in
  user. Without a signed-in user they are shared, so clients must generate
  unique keys.

## Configuration

Register `IdempotencyServiceProvider` with `HttpServiceProvider` and
`DatabaseServiceProvider`, create the table, and delete expired keys daily
from the [scheduler](scheduler.md):

```
php bin/zana idempotency:install
php bin/zana migrate
php bin/zana idempotency:prune
```

```php
// config/idempotency.php
return [
    'ttl' => 86400,       // seconds a key and its response are kept
    'lock_seconds' => 60, // how long a running request holds its key
];
```

`check` reports an error when the keys table cannot be read, and warns
about an `#[Idempotent]` handler whose route does not run the middleware.
`inspect idempotency` shows the stored, running and expired keys.

The package is tested on SQLite, MySQL and PostgreSQL, with four processes
sending the same request at the same moment.

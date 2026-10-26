# Sessions

`mj-zana/session` (2.2) stores server-side sessions for websites:
a back office's login, flash messages after a redirect, a basket. The data
lives in the application's PSR-16 cache; the browser only holds a random id
in an `HttpOnly`, `SameSite=Lax` cookie.

```
composer require mj-zana/session
```

## Reading and writing

<!-- example: examples/guides/session.php#requests -->
```php
$public = $visit(null, static fn (SessionInterface $session): string => 'a public page');
echo 'Public page sets a cookie: ', $public->hasHeader('Set-Cookie') ? 'yes' : 'no', "\n";

$saved = $visit(null, static function (SessionInterface $session): string {
    $session->set('user', 'ada');
    $session->flash('status', 'Saved.');   // for the next request only

    return 'saved';
});
$setCookie = $saved->getHeaderLine('Set-Cookie');
echo 'Set-Cookie: ', preg_replace('/=[0-9a-f]{64}/', '=<random id>', $setCookie), "\n";

$cookie = substr(strstr($setCookie, ';', true), strlen('zana_session='));
foreach ([1, 2] as $n) {
    echo $visit($cookie, static fn (SessionInterface $session): string => "Next request {$n}: user " . $session->get('user')
        . ', status ' . var_export($session->getFlash('status'), true))->getBody(), "\n";
}
```

<!-- example: examples/guides/session.out -->
```text
Public page sets a cookie: no
Set-Cookie: zana_session=<random id>; Path=/; HttpOnly; SameSite=Lax; Secure
Next request 1: user ada, status 'Saved.'
Next request 2: user ada, status NULL
```

Rules worth knowing:

- **No session, no cookie.** A request that never writes to the session
  sends no `Set-Cookie`, so public pages stay cacheable by a CDN or the
  [page cache](http.md#page-cache).
- **Flash values last one request**: the next request reads them, then
  they are gone.
- **Regenerate on login.** `regenerate()` gives the session a new id and
  keeps its data; `invalidate()` empties it on logout. The security
  package's `SessionLogin` does both for you (see
  [Security](security.md#signing-in-with-a-session)).
- The cookie is `Secure` when the request is HTTPS, including behind a
  trusted proxy (see [HTTP](http.md#behind-a-proxy)).

## In an application

Register `SessionServiceProvider`, add `SessionMiddleware` to the pipeline
before anything that reads the session (route middleware works well for a
back office), and inject `Zana\Session\SessionInterface` where you need
the current session:

```php
// config/session.php
return [
    'cookie' => 'zana_session',
    'lifetime' => 7200,        // seconds of inactivity
];
```

```php
public function __construct(private SessionInterface $session) {}

public function __invoke(): ResponseInterface
{
    $this->session->flash('status', 'Saved.');
    // ...
}
```

The store is `SessionStoreInterface`. `CacheSessionStore` is the default;
use a file or database cache in production so sessions survive a restart
and are shared between servers.

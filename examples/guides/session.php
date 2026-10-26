<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zana\Cache\ArrayCache;
use Zana\Session\CacheSessionStore;
use Zana\Session\SessionContext;
use Zana\Session\SessionInterface;
use Zana\Session\SessionMiddleware;

// SessionServiceProvider builds the store from session.* and the application's PSR-16 cache.
$store = new CacheSessionStore(new ArrayCache());

// One request through SessionMiddleware; $controller plays the controller.
$visit = static function (?string $cookie, Closure $controller) use ($store): ResponseInterface {
    $context = new SessionContext();
    $request = (new ServerRequest('GET', 'https://acme.example/admin'))->withCookieParams($cookie === null ? [] : ['zana_session' => $cookie]);

    return (new SessionMiddleware($store, $context))->process($request, new class ($context, $controller) implements RequestHandlerInterface {
        public function __construct(private SessionContext $context, private Closure $controller)
        {
        }

        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            return new Response(200, [], ($this->controller)($this->context->session()));
        }
    });
};

// region: requests
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
// endregion: requests

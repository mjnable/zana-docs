<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Application;
use Zana\Http\Exception\NotFoundHttpException;
use Zana\Http\HttpKernel;
use Zana\Http\HttpServiceProvider;
use Zana\Http\ResponseFactory;

// region: middleware
final readonly class PoweredBy implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request)->withHeader('X-Powered-By', 'Zana');
    }
}
// endregion: middleware

// region: handler
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
// endregion: handler

$base = sys_get_temp_dir() . '/zana-docs-http';
@mkdir("{$base}/config", 0777, true);

// region: config
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
// endregion: config

// region: requests
$ok = $kernel->handle(new ServerRequest('GET', '/', ['X-Correlation-Id' => 'trace-1']));
echo $ok->getStatusCode(), ' ', $ok->getBody(), "\n";
echo 'X-Powered-By: ', $ok->getHeaderLine('X-Powered-By'), "\n";
echo 'X-Correlation-Id: ', $ok->getHeaderLine('X-Correlation-Id'), "\n";

$missing = $kernel->handle(new ServerRequest('GET', '/nope', ['Accept' => 'application/json']));
echo $missing->getStatusCode(), ' ', $missing->getHeaderLine('Content-Type'), "\n";
echo $missing->getBody(), "\n";
// endregion: requests

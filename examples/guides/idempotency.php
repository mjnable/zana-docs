<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zana\Database\ConnectionFactory;
use Zana\Http\Exception\HttpException;
use Zana\Idempotency\Idempotent;
use Zana\Idempotency\IdempotencyMiddleware;
use Zana\Idempotency\IdempotencyStore;
use Zana\Idempotency\IdempotencyTableMigration;

// region: handler
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
// endregion: handler

$db = ConnectionFactory::create(['driver' => 'sqlite', 'database' => ':memory:']);
(new IdempotencyTableMigration())->up($db->schema());   // php bin/zana idempotency:install, then migrate
$action = new PayOrderAction();
$router = new readonly class ($action) implements RequestHandlerInterface {
    public function __construct(private PayOrderAction $action)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->action)($request);
    }
};
// In an application, IdempotencyServiceProvider registers the middleware, with the signed-in user.
$factory = new Psr17Factory();
$middleware = new IdempotencyMiddleware(new IdempotencyStore($db), $factory, $factory, static fn (): string => 'buyer-42');

// region: retry
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
// endregion: retry

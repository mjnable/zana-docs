<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Application;
use Zana\Http\HttpKernel;
use Zana\Http\HttpServiceProvider;
use Zana\Routing\Attribute\Route;
use Zana\Routing\Router;
use Zana\Routing\RoutingServiceProvider;
use Zana\Routing\UrlGenerator;

// region: attributes
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
// endregion: attributes

$app = Application::configure(sys_get_temp_dir() . '/zana-docs-routing')
    ->withProviders(HttpServiceProvider::class, RoutingServiceProvider::class)
    ->withServices(static fn (ServiceCollectionInterface $s) => $s->transient(PatientController::class))
    ->build();
$container = $app->container();

// region: routes
$router = $container->get(Router::class);
$router->loadAttributes(PatientController::class);   // controllers must be registered services

$router->get('/health', static fn (): string => 'ok')->name('health');
$router->group('/api/reports', static function (Router $r): void {
    $r->get('/{year}', static fn (int $year): array => ['year' => $year])->where('year', '\d{4}');
});
// endregion: routes

// region: dispatch
$kernel = $container->get(HttpKernel::class);
foreach (['/api/patients?page=2', '/api/patients/7', '/api/patients/abc', '/api/reports/2026', '/health'] as $path) {
    $response = $kernel->handle(new ServerRequest('GET', $path, ['Accept' => 'application/json']));
    echo rtrim(str_pad($path, 22) . $response->getStatusCode() . ' ' . ($response->getStatusCode() < 400 ? $response->getBody() : '')), "\n";
}

$urls = $container->get(UrlGenerator::class);
echo $urls->generate('patients.show', ['id' => 42], ['tab' => 'bmi']), "\n";
// endregion: dispatch

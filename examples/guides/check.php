<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Cache\CacheServiceProvider;
use Zana\Console\ConsoleKernel;
use Zana\Console\ConsoleServiceProvider;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Application;
use Zana\Core\ApplicationInterface;
use Zana\Core\Inspection\ApplicationCheck;
use Zana\Core\Inspection\Finding;
use Zana\Core\Inspection\Severity;
use Zana\Http\HttpServiceProvider;
use Zana\Routing\Router;
use Zana\Routing\RoutingServiceProvider;
use Zana\Testing\ConsoleTester;

final class ShelfController
{
    /**
     * @return list<string>
     */
    public function index(): array
    {
        return ['A1', 'A2'];
    }
}

// region: contribute
// An application or package adds its own checks: a service tagged ApplicationCheck::TAG.
final class OpeningHoursCheck implements ApplicationCheck
{
    public function findings(ApplicationInterface $application): array
    {
        $hours = $application->config()->get('shop.opening_hours', '');

        return $hours === ''
            ? [new Finding('shop.opening_hours', Severity::Warning, 'The opening hours are not set.', hint: "Set shop.opening_hours in config/shop.php.")]
            : [];
    }
}
// endregion: contribute

$app = Application::configure(sys_get_temp_dir() . '/zana-docs-check')
    ->withProviders(HttpServiceProvider::class, RoutingServiceProvider::class, CacheServiceProvider::class, ConsoleServiceProvider::class)
    ->withServices(static fn (ServiceCollectionInterface $s) => $s
        ->singleton(OpeningHoursCheck::class)->tag(ApplicationCheck::TAG, OpeningHoursCheck::class))
    ->build();

// The mistake: ShelfController is routed to but was never registered.
$app->container()->get(Router::class)->get('/shelves', [ShelfController::class, 'index']);

// region: check
$console = new ConsoleTester($app->container()->get(ConsoleKernel::class));
$result = $console->run('check --format=json');

$report = json_decode($result->output(), true);
echo "{$report['schema']}: {$report['status']} [exit {$result->exitCode()}]\n";
foreach ($report['findings'] as $finding) {
    echo "{$finding['severity']} {$finding['id']}: {$finding['message']}\n  hint: {$finding['hint']}\n";
}
// endregion: check

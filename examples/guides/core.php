<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Application;
use Zana\Core\Module;
use Zana\Core\ServiceProvider;

final class Catalog
{
    /** @var list<string> */
    public array $products = [];
}

// region: modules
final class CatalogModule extends Module
{
    public function name(): string
    {
        return 'Catalog';
    }

    public function register(ServiceCollectionInterface $services): void
    {
        $services->singleton(Catalog::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $container->get(Catalog::class)->products[] = 'Scale';
        echo "booted Catalog\n";
    }
}

final class OrdersModule extends Module
{
    public function name(): string
    {
        return 'Orders';
    }

    public function dependsOn(): array
    {
        return [CatalogModule::class];   // booted after Catalog, and Catalog must be enabled
    }

    public function register(ServiceCollectionInterface $services): void
    {
    }

    public function boot(ContainerInterface $container): void
    {
        echo 'booted Orders, catalog has: ', implode(', ', $container->get(Catalog::class)->products), "\n";
    }
}
// endregion: modules

final class AuditProvider extends ServiceProvider
{
    public function register(ServiceCollectionInterface $services): void
    {
    }

    public function boot(ContainerInterface $container): void
    {
        echo "booted AuditProvider\n";
    }
}

// region: build
$app = Application::configure(sys_get_temp_dir() . '/zana-docs-core')
    ->withEnvironment('development')
    ->withProviders(AuditProvider::class)
    ->withModules(OrdersModule::class, CatalogModule::class)   // usually listed in config/modules.php
    ->build();

echo 'environment: ', $app->environment()->name, ', debug: ', var_export($app->environment()->debug, true), "\n";
echo 'modules: ', implode(', ', array_map(static fn (Module $m): string => $m->name(), $app->modules())), "\n";

$app->terminate();   // disposes the container
// endregion: build

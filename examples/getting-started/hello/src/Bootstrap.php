<?php

declare(strict_types=1);

namespace Hello;

use Zana\Console\ConsoleServiceProvider;
use Zana\Core\Application;
use Zana\Core\ApplicationBuilder;
use Zana\Http\HttpServiceProvider;
use Zana\Routing\RoutingServiceProvider;

final class Bootstrap
{
    public static function builder(string $basePath): ApplicationBuilder
    {
        return Application::configure($basePath)
            ->withProviders(
                HttpServiceProvider::class,
                RoutingServiceProvider::class,
                ConsoleServiceProvider::class,
            );
    }
}

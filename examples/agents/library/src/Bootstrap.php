<?php

declare(strict_types=1);

namespace App;

use Zana\Console\ConsoleServiceProvider;
use Zana\Core\Application;
use Zana\Core\ApplicationBuilder;
use Zana\Database\DatabaseServiceProvider;
use Zana\Http\HttpServiceProvider;
use Zana\Maker\MakerServiceProvider;
use Zana\Mediator\MediatorServiceProvider;
use Zana\Routing\RoutingServiceProvider;
use Zana\Serialization\SerializationServiceProvider;
use Zana\Validation\ValidationServiceProvider;

/**
 * The library API built in "Working with AI coding agents": the packages a
 * JSON API with a database needs, and the make: commands in development.
 */
final class Bootstrap
{
    public static function builder(string $basePath): ApplicationBuilder
    {
        return Application::configure($basePath)
            ->withProviders(
                HttpServiceProvider::class,
                RoutingServiceProvider::class,
                MediatorServiceProvider::class,
                SerializationServiceProvider::class,
                ValidationServiceProvider::class,
                DatabaseServiceProvider::class,
                ConsoleServiceProvider::class,
                ...(class_exists(MakerServiceProvider::class) ? [MakerServiceProvider::class] : []),
            );
    }
}

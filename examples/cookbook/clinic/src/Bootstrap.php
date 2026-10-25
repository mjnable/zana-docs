<?php

declare(strict_types=1);

namespace Clinic;

use Clinic\Http\JsonInput;
use Clinic\Http\ValidationBehavior;
use Zana\Console\ConsoleServiceProvider;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Application;
use Zana\Core\ApplicationBuilder;
use Zana\Database\DatabaseServiceProvider;
use Zana\Events\EventsServiceProvider;
use Zana\Http\HttpServiceProvider;
use Zana\Logging\LoggingServiceProvider;
use Zana\Mediator\MediatorServiceProvider;
use Zana\Routing\RoutingServiceProvider;
use Zana\Serialization\SerializationServiceProvider;
use Zana\Validation\ValidationServiceProvider;

final class Bootstrap
{
    public static function builder(string $basePath): ApplicationBuilder
    {
        return Application::configure($basePath)
            ->withProviders(
                LoggingServiceProvider::class,
                EventsServiceProvider::class,
                HttpServiceProvider::class,
                RoutingServiceProvider::class,
                ConsoleServiceProvider::class,
                DatabaseServiceProvider::class,
                MediatorServiceProvider::class,
                ValidationServiceProvider::class,
                SerializationServiceProvider::class,
            )
            ->withServices(static fn (ServiceCollectionInterface $s) => $s
                ->singleton(ValidationBehavior::class)
                ->singleton(JsonInput::class));
    }
}

<?php

declare(strict_types=1);

namespace Website;

use Zana\Cache\CacheServiceProvider;
use Zana\Communication\CommunicationServiceProvider;
use Zana\Core\Application;
use Zana\Core\ApplicationBuilder;
use Zana\Http\HttpServiceProvider;
use Zana\Routing\RoutingServiceProvider;
use Zana\Security\SecurityServiceProvider;
use Zana\Translation\TranslationServiceProvider;
use Zana\Validation\ValidationServiceProvider;
use Zana\View\ViewServiceProvider;

final class Bootstrap
{
    public static function builder(string $basePath): ApplicationBuilder
    {
        return Application::configure($basePath)
            ->withProviders(
                HttpServiceProvider::class,
                CacheServiceProvider::class,       // for the contact form's rate limit
                RoutingServiceProvider::class,
                TranslationServiceProvider::class,
                SecurityServiceProvider::class,
                ValidationServiceProvider::class,
                CommunicationServiceProvider::class,
                ViewServiceProvider::class,
            );
    }
}

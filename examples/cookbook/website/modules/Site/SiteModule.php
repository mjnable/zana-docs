<?php

declare(strict_types=1);

namespace Modules\Site;

use Modules\Site\Presentation\Web\PageController;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Module;
use Zana\Routing\Router;

final class SiteModule extends Module
{
    public function name(): string
    {
        return 'Site';
    }

    public function register(ServiceCollectionInterface $services): void
    {
        $services->transient(PageController::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $router = $container->get(Router::class);
        assert($router instanceof Router);

        // region: routes
        $router->get('/', [PageController::class, 'root']);
        $router->localized(['en' => '/', 'fr' => '/'], [PageController::class, 'home'])->name('home');
        $router->localized(['en' => '/about', 'fr' => '/a-propos'], [PageController::class, 'about'])->name('about');
        // endregion: routes
    }
}

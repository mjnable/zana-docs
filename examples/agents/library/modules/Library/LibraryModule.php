<?php

declare(strict_types=1);

namespace Modules\Library;

use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Module;

final class LibraryModule extends Module
{
    public function name(): string
    {
        return 'Library';
    }

    /**
     * @return list<class-string<Module>>
     */
    public function dependsOn(): array
    {
        return [];
    }

    public function register(ServiceCollectionInterface $services): void
    {
        // Register this module's services, e.g.
        // $services->scoped(PatientRepositoryInterface::class, PdoPatientRepository::class);
        $services->scoped(\Modules\Library\Domain\BookRepositoryInterface::class, \Modules\Library\Infrastructure\Persistence\DatabaseBookRepository::class);
        $services->scoped(\Modules\Library\Application\Features\AddBook\AddBookHandler::class);
        $services->singleton(\Modules\Library\Presentation\Api\ApiInput::class);
        $services->transient(\Modules\Library\Presentation\Api\AddBookAction::class);
        $services->scoped(\Modules\Library\Application\Features\ListBooks\ListBooksHandler::class);
        $services->transient(\Modules\Library\Presentation\Api\ListBooksAction::class);
        // zana:maker services (the make: commands add lines above; keep this line)
    }

    public function boot(ContainerInterface $container): void
    {
        // Map handlers, add routes and listeners once the container is built.
        $container->get(\Zana\Mediator\HandlerMap::class)->register(\Modules\Library\Application\Features\AddBook\AddBookHandler::class);
        $container->get(\Zana\Routing\Router::class)->loadAttributes(\Modules\Library\Presentation\Api\AddBookAction::class);
        $container->get(\Zana\Mediator\HandlerMap::class)->register(\Modules\Library\Application\Features\ListBooks\ListBooksHandler::class);
        $container->get(\Zana\Routing\Router::class)->loadAttributes(\Modules\Library\Presentation\Api\ListBooksAction::class);
        // zana:maker boot (the make: commands add lines above; keep this line)
    }
}

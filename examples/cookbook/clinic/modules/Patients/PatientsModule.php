<?php

declare(strict_types=1);

namespace Modules\Patients;

use Modules\Patients\Application\PatientDirectoryService;
use Modules\Patients\Application\RegisterPatientHandler;
use Modules\Patients\Contracts\PatientDirectory;
use Modules\Patients\Domain\PatientRepositoryInterface;
use Modules\Patients\Infrastructure\DatabasePatientRepository;
use Modules\Patients\Presentation\Api\RegisterPatientAction;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Module;
use Zana\Mediator\HandlerMap;
use Zana\Routing\Router;

final class PatientsModule extends Module
{
    public function name(): string
    {
        return 'Patients';
    }

    public function register(ServiceCollectionInterface $services): void
    {
        $services
            ->scoped(PatientRepositoryInterface::class, DatabasePatientRepository::class)
            ->scoped(PatientDirectory::class, PatientDirectoryService::class)   // the public contract
            ->scoped(RegisterPatientHandler::class)
            ->transient(RegisterPatientAction::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $container->get(HandlerMap::class)->register(RegisterPatientHandler::class);
        $container->get(Router::class)->post('/api/patients', RegisterPatientAction::class);
    }
}

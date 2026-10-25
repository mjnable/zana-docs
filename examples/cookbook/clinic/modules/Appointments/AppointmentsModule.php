<?php

declare(strict_types=1);

namespace Modules\Appointments;

use Modules\Appointments\Application\BookAppointmentHandler;
use Modules\Appointments\Application\NotifyClinic;
use Modules\Appointments\Domain\AppointmentRepositoryInterface;
use Modules\Appointments\Infrastructure\DatabaseAppointmentRepository;
use Modules\Appointments\Presentation\Api\BookAppointmentAction;
use Modules\Patients\PatientsModule;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Module;
use Zana\Events\ListenerProvider;
use Zana\Mediator\HandlerMap;
use Zana\Routing\Router;

// region: module
final class AppointmentsModule extends Module
{
    public function name(): string
    {
        return 'Appointments';
    }

    public function dependsOn(): array
    {
        return [PatientsModule::class];      // required to use Patients' contracts
    }

    public function register(ServiceCollectionInterface $services): void
    {
        $services
            ->scoped(AppointmentRepositoryInterface::class, DatabaseAppointmentRepository::class)
            ->scoped(BookAppointmentHandler::class)
            ->singleton(NotifyClinic::class)
            ->transient(BookAppointmentAction::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $container->get(HandlerMap::class)->register(BookAppointmentHandler::class);
        $container->get(ListenerProvider::class)->addListenerClass(NotifyClinic::class);
        $container->get(Router::class)->post('/api/appointments', BookAppointmentAction::class);
    }
}
// endregion: module

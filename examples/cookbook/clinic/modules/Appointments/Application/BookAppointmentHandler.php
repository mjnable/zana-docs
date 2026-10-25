<?php

declare(strict_types=1);

namespace Modules\Appointments\Application;

use Modules\Appointments\Domain\Appointment;
use Modules\Appointments\Domain\AppointmentId;
use Modules\Appointments\Domain\AppointmentRepositoryInterface;
use Modules\Patients\Contracts\PatientDirectory;
use Psr\EventDispatcher\EventDispatcherInterface;
use Zana\Ddd\Domain\DomainException;
use Zana\Ddd\Domain\UnitOfWorkInterface;
use Zana\Mediator\Attribute\Handles;

// region: handler
#[Handles(BookAppointment::class)]
final readonly class BookAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointments,
        private PatientDirectory $patients,            // the other module's contract, nothing more
        private UnitOfWorkInterface $unitOfWork,
        private EventDispatcherInterface $events,
    ) {
    }

    public function __invoke(BookAppointment $command): AppointmentView
    {
        $patient = $this->patients->find($command->patientId)
            ?? throw new AppointmentRejected(['patient_id' => 'There is no such patient.']);

        try {
            $appointment = $this->unitOfWork->transactional(function () use ($command): Appointment {
                $appointment = Appointment::book(AppointmentId::generate(), $command->patientId, $command->startsAt, $command->reason, new \DateTimeImmutable());
                $this->appointments->add($appointment);

                return $appointment;
            });
        } catch (DomainException $e) {
            throw new AppointmentRejected(['starts_at' => $e->getMessage()], $e);
        }

        foreach ($appointment->releaseEvents() as $event) {   // only after the commit
            $this->events->dispatch($event);
        }

        return new AppointmentView((string) $appointment->id(), $patient->id, $patient->name, $appointment->startsAt->format(\DATE_ATOM), $appointment->reason);
    }
}
// endregion: handler

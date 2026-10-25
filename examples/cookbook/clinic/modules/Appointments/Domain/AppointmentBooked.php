<?php

declare(strict_types=1);

namespace Modules\Appointments\Domain;

use Zana\Ddd\Domain\DomainEvent;

final class AppointmentBooked extends DomainEvent
{
    public function __construct(public readonly string $appointmentId, public readonly string $patientId, public readonly \DateTimeImmutable $startsAt)
    {
        parent::__construct();
    }
}

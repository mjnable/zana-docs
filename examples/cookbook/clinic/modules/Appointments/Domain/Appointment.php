<?php

declare(strict_types=1);

namespace Modules\Appointments\Domain;

use Zana\Ddd\Domain\AggregateRoot;
use Zana\Ddd\Domain\DomainException;

// region: aggregate
/**
 * An appointment belongs to a patient by id only: the patient lives in
 * another module, so this module stores the id and nothing else.
 */
final class Appointment extends AggregateRoot
{
    private function __construct(
        AppointmentId $id,
        public readonly string $patientId,
        public readonly \DateTimeImmutable $startsAt,
        public readonly string $reason,
    ) {
        parent::__construct($id);
    }

    public static function book(AppointmentId $id, string $patientId, \DateTimeImmutable $startsAt, string $reason, \DateTimeImmutable $now): self
    {
        if ($startsAt <= $now) {
            throw new DomainException('An appointment must be in the future.');
        }

        $appointment = new self($id, $patientId, $startsAt, $reason);
        $appointment->recordEvent(new AppointmentBooked((string) $id, $patientId, $startsAt));

        return $appointment;
    }
    // endregion: aggregate

    public static function restore(AppointmentId $id, string $patientId, \DateTimeImmutable $startsAt, string $reason): self
    {
        return new self($id, $patientId, $startsAt, $reason);
    }

    public function id(): AppointmentId
    {
        assert($this->id instanceof AppointmentId);

        return $this->id;
    }
}

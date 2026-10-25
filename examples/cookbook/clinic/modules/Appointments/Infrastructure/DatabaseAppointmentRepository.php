<?php

declare(strict_types=1);

namespace Modules\Appointments\Infrastructure;

use Modules\Appointments\Domain\Appointment;
use Modules\Appointments\Domain\AppointmentId;
use Modules\Appointments\Domain\AppointmentRepositoryInterface;
use Zana\Database\ConnectionInterface;
use Zana\Ddd\Domain\AggregateRoot;
use Zana\Ddd\Domain\EntityNotFoundException;

final readonly class DatabaseAppointmentRepository implements AppointmentRepositoryInterface
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function find(mixed $id): ?Appointment
    {
        $row = $this->db->table('appointments')->where('id', (string) $id)->first();

        return $row === null ? null : Appointment::restore(
            AppointmentId::fromString((string) $row['id']),
            (string) $row['patient_id'],
            new \DateTimeImmutable((string) $row['starts_at'], new \DateTimeZone('UTC')),
            (string) $row['reason'],
        );
    }

    public function get(mixed $id): Appointment
    {
        return $this->find($id) ?? throw new EntityNotFoundException("There is no appointment {$id}.");
    }

    public function add(AggregateRoot $aggregate): void
    {
        assert($aggregate instanceof Appointment);
        $this->db->table('appointments')->insert([
            'id' => (string) $aggregate->id(),
            'patient_id' => $aggregate->patientId,          // no foreign key: patients belong to another module
            'starts_at' => $aggregate->startsAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'reason' => $aggregate->reason,
        ]);
    }

    public function remove(AggregateRoot $aggregate): void
    {
        assert($aggregate instanceof Appointment);
        $this->db->table('appointments')->where('id', (string) $aggregate->id())->delete();
    }
}

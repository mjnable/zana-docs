<?php

declare(strict_types=1);

namespace Modules\Patients\Infrastructure;

use Modules\Patients\Domain\Patient;
use Modules\Patients\Domain\PatientId;
use Modules\Patients\Domain\PatientRepositoryInterface;
use Zana\Database\ConnectionInterface;
use Zana\Ddd\Domain\AggregateRoot;
use Zana\Ddd\Domain\EntityNotFoundException;

final readonly class DatabasePatientRepository implements PatientRepositoryInterface
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function find(mixed $id): ?Patient
    {
        $row = $this->db->table('patients')->where('id', (string) $id)->first();

        return $row === null ? null : Patient::restore(PatientId::fromString((string) $row['id']), (string) $row['name']);
    }

    public function get(mixed $id): Patient
    {
        return $this->find($id) ?? throw new EntityNotFoundException("There is no patient {$id}.");
    }

    public function add(AggregateRoot $aggregate): void
    {
        assert($aggregate instanceof Patient);
        $this->db->table('patients')->insert(['id' => (string) $aggregate->id(), 'name' => $aggregate->name]);
    }

    public function remove(AggregateRoot $aggregate): void
    {
        assert($aggregate instanceof Patient);
        $this->db->table('patients')->where('id', (string) $aggregate->id())->delete();
    }
}

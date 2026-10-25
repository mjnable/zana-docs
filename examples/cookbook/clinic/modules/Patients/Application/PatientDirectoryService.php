<?php

declare(strict_types=1);

namespace Modules\Patients\Application;

use Modules\Patients\Contracts\PatientDirectory;
use Modules\Patients\Contracts\PatientSnapshot;
use Modules\Patients\Domain\PatientId;
use Modules\Patients\Domain\PatientRepositoryInterface;

/**
 * The implementation of the module's public contract. Other modules never see this class.
 */
final readonly class PatientDirectoryService implements PatientDirectory
{
    public function __construct(private PatientRepositoryInterface $patients)
    {
    }

    public function find(string $patientId): ?PatientSnapshot
    {
        if (!PatientId::isValidValue($patientId)) {
            return null;
        }
        $patient = $this->patients->find(PatientId::fromString($patientId));

        return $patient === null ? null : new PatientSnapshot((string) $patient->id(), $patient->name);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Patients\Contracts;

/**
 * The Patients module's public contract. Modules that declare Patients as a
 * dependency inject it; the Patients module implements and registers it.
 */
interface PatientDirectory
{
    public function find(string $patientId): ?PatientSnapshot;
}

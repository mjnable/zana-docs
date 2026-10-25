<?php

declare(strict_types=1);

namespace Modules\Patients\Application;

use Modules\Patients\Contracts\PatientSnapshot;
use Modules\Patients\Domain\Patient;
use Modules\Patients\Domain\PatientId;
use Modules\Patients\Domain\PatientRepositoryInterface;
use Zana\Ddd\Domain\UnitOfWorkInterface;
use Zana\Mediator\Attribute\Handles;

#[Handles(RegisterPatient::class)]
final readonly class RegisterPatientHandler
{
    public function __construct(private PatientRepositoryInterface $patients, private UnitOfWorkInterface $unitOfWork)
    {
    }

    public function __invoke(RegisterPatient $command): PatientSnapshot
    {
        $patient = $this->unitOfWork->transactional(function () use ($command): Patient {
            $patient = Patient::register(PatientId::generate(), $command->name);
            $this->patients->add($patient);

            return $patient;
        });

        return new PatientSnapshot((string) $patient->id(), $patient->name);
    }
}

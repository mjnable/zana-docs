<?php

declare(strict_types=1);

namespace Modules\Patients\Contracts;

/**
 * What other modules may know about a patient: plain values, no domain objects.
 */
final readonly class PatientSnapshot
{
    public function __construct(public string $id, public string $name)
    {
    }
}

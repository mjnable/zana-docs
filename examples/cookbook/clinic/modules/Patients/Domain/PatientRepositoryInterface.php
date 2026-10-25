<?php

declare(strict_types=1);

namespace Modules\Patients\Domain;

use Zana\Ddd\Domain\RepositoryInterface;

/**
 * @extends RepositoryInterface<Patient>
 */
interface PatientRepositoryInterface extends RepositoryInterface
{
}

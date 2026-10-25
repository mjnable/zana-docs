<?php

declare(strict_types=1);

namespace Modules\Appointments\Domain;

use Zana\Ddd\Domain\RepositoryInterface;

/**
 * @extends RepositoryInterface<Appointment>
 */
interface AppointmentRepositoryInterface extends RepositoryInterface
{
}

<?php

declare(strict_types=1);

namespace Modules\Appointments\Application;

/**
 * What the application hands to presentations: plain values, never the aggregate.
 */
final readonly class AppointmentView
{
    public function __construct(
        public string $id,
        public string $patientId,
        public string $patientName,
        public string $startsAt,
        public string $reason,
    ) {
    }
}

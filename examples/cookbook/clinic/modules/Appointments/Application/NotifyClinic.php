<?php

declare(strict_types=1);

namespace Modules\Appointments\Application;

use Modules\Appointments\Domain\AppointmentBooked;
use Psr\Log\LoggerInterface;
use Zana\Events\AsListener;

// region: listener
#[AsListener]
final readonly class NotifyClinic
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function __invoke(AppointmentBooked $event): void
    {
        $this->logger->info('Appointment {id} booked for {at}', ['id' => $event->appointmentId, 'at' => $event->startsAt->format('Y-m-d H:i')]);
    }
}
// endregion: listener

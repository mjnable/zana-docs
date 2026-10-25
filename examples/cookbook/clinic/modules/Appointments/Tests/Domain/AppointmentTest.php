<?php

declare(strict_types=1);

namespace Modules\Appointments\Tests\Domain;

use Modules\Appointments\Domain\Appointment;
use Modules\Appointments\Domain\AppointmentBooked;
use Modules\Appointments\Domain\AppointmentId;
use PHPUnit\Framework\TestCase;
use Zana\Ddd\Domain\DomainException;

// region: domain-test
final class AppointmentTest extends TestCase
{
    public function testBookingRecordsAnEvent(): void
    {
        $now = new \DateTimeImmutable('2030-01-01 09:00');
        $appointment = Appointment::book(AppointmentId::generate(), 'patient-1', $now->modify('+1 day'), 'Check-up', $now);

        $events = $appointment->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(AppointmentBooked::class, $events[0]);
    }

    public function testAppointmentsMustBeInTheFuture(): void
    {
        $now = new \DateTimeImmutable('2030-01-01 09:00');

        $this->expectException(DomainException::class);
        Appointment::book(AppointmentId::generate(), 'patient-1', $now, 'Too late', $now);
    }
}
// endregion: domain-test

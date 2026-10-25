<?php

declare(strict_types=1);

namespace Modules\Appointments\Tests\Application;

use Modules\Appointments\Application\AppointmentRejected;
use Modules\Appointments\Application\BookAppointment;
use Modules\Appointments\Application\BookAppointmentHandler;
use Modules\Appointments\Domain\Appointment;
use Modules\Appointments\Domain\AppointmentBooked;
use Modules\Appointments\Domain\AppointmentRepositoryInterface;
use Modules\Patients\Contracts\PatientDirectory;
use Modules\Patients\Contracts\PatientSnapshot;
use PHPUnit\Framework\TestCase;
use Zana\Ddd\Domain\AggregateRoot;
use Zana\Ddd\Domain\UnitOfWorkInterface;
use Zana\Testing\EventRecorder;

// region: application-test
final class BookAppointmentHandlerTest extends TestCase
{
    public function testAKnownPatientGetsTheAppointment(): void
    {
        [$handler, $repository, $events] = $this->handler(new PatientSnapshot('p-1', 'Ada Lovelace'));

        $view = $handler(new BookAppointment('p-1', new \DateTimeImmutable('+1 day'), 'Check-up'));

        $this->assertSame('Ada Lovelace', $view->patientName);
        $this->assertCount(1, $repository->saved);
        $events->assertDispatched(AppointmentBooked::class, times: 1);
    }

    public function testAnUnknownPatientIsRejected(): void
    {
        [$handler, $repository] = $this->handler(null);

        try {
            $handler(new BookAppointment('nobody', new \DateTimeImmutable('+1 day'), 'Check-up'));
            $this->fail('Expected a rejection.');
        } catch (AppointmentRejected $e) {
            $this->assertSame(['patient_id' => 'There is no such patient.'], $e->errors);
        }
        $this->assertSame([], $repository->saved);
    }

    /**
     * @return array{BookAppointmentHandler, object{saved: list<Appointment>}&AppointmentRepositoryInterface, EventRecorder}
     */
    private function handler(?PatientSnapshot $patient): array
    {
        $directory = new class ($patient) implements PatientDirectory {   // a fake of the other module's contract
            public function __construct(private ?PatientSnapshot $patient)
            {
            }

            public function find(string $patientId): ?PatientSnapshot
            {
                return $this->patient?->id === $patientId ? $this->patient : null;
            }
        };
        $repository = new class () implements AppointmentRepositoryInterface {
            /** @var list<Appointment> */
            public array $saved = [];

            public function find(mixed $id): ?Appointment
            {
                return null;
            }

            public function get(mixed $id): Appointment
            {
                throw new \LogicException('Not needed.');
            }

            public function add(AggregateRoot $aggregate): void
            {
                assert($aggregate instanceof Appointment);
                $this->saved[] = $aggregate;
            }

            public function remove(AggregateRoot $aggregate): void
            {
            }
        };
        $unitOfWork = new class () implements UnitOfWorkInterface {
            public function begin(): void
            {
            }

            public function commit(): void
            {
            }

            public function rollback(): void
            {
            }

            public function transactional(callable $callback): mixed
            {
                return $callback();
            }
        };
        $events = new EventRecorder();

        return [new BookAppointmentHandler($repository, $directory, $unitOfWork, $events), $repository, $events];
    }
}
// endregion: application-test

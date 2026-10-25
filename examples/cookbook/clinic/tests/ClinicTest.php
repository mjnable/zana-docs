<?php

declare(strict_types=1);

namespace Clinic\Tests;

use Clinic\Bootstrap;
use Zana\Core\ApplicationInterface;
use Zana\Database\Migration\Migrator;
use Zana\Logging\Handler\ArrayHandler;
use Zana\Logging\LogManager;
use Zana\Testing\ApplicationTestCase;

// region: integration-test
final class ClinicTest extends ApplicationTestCase
{
    private string $database = '';

    protected function createApplication(): ApplicationInterface
    {
        // Each test gets its own SQLite file, set the way a deployment would set it.
        $this->database = sys_get_temp_dir() . '/clinic-' . bin2hex(random_bytes(4)) . '.sqlite';
        putenv("APP_DATABASE__CONNECTIONS__SQLITE__DATABASE={$this->database}");

        $app = Bootstrap::builder(dirname(__DIR__))->withEnvironment('testing')->build();
        $app->container()->get(Migrator::class)->migrate();

        return $app;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('APP_DATABASE__CONNECTIONS__SQLITE__DATABASE');
        @unlink($this->database);
    }

    public function testBookingAnAppointmentForARegisteredPatient(): void
    {
        $patientId = $this->http()->post('/api/patients', ['name' => 'Ada Lovelace'])->assertCreated()->jsonPath('id');

        $this->http()->post('/api/appointments', ['patient_id' => $patientId, 'starts_at' => '2030-01-15T09:30:00+00:00', 'reason' => 'Check-up'])
            ->assertCreated()
            ->assertJson(['patientName' => 'Ada Lovelace', 'startsAt' => '2030-01-15T09:30:00+00:00']);

        $handler = $this->service(LogManager::class)->handler();
        $this->assertInstanceOf(ArrayHandler::class, $handler);
        $this->assertTrue($handler->hasRecord('info', 'booked for 2030-01-15 09:30'), 'the listener ran');
    }

    public function testInvalidBookingsAreRefusedWithFieldErrors(): void
    {
        $this->http()->post('/api/appointments', ['patient_id' => 'nobody', 'starts_at' => '2030-01-15T09:30:00+00:00', 'reason' => 'Check-up'])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['patient_id' => ['There is no such patient.']]);

        $patientId = $this->http()->post('/api/patients', ['name' => 'Grace Hopper'])->jsonPath('id');
        $this->http()->post('/api/appointments', ['patient_id' => $patientId, 'starts_at' => '2001-01-01T09:00:00+00:00', 'reason' => 'Check-up'])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['starts_at' => ['An appointment must be in the future.']]);

        $this->http()->post('/api/appointments', ['patient_id' => $patientId, 'starts_at' => 'soon', 'reason' => ''])
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_at', ['Must be a date and time.']);
    }
}
// endregion: integration-test

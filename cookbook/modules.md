# The module cookbook

Zana applications are built from **modules**: self-contained business
capabilities with their own layers, persistence and presentations. This
cookbook builds a small clinic application with two modules:

- **Patients** registers patients and offers a public contract.
- **Appointments** books appointments for patients, using only that
  contract.

The complete application is in
[`examples/cookbook/clinic`](../examples/cookbook/clinic). Every listing
below comes from it, and its tests run with this documentation's test
suite. The [BMI reference application](../getting-started.md#where-next)
uses the same patterns at a larger scale.

## 1. Anatomy of a module

```
modules/Appointments/
├── AppointmentsModule.php     the module class: registration and boot
├── Contracts/                 the only namespace other modules may use
├── Domain/                    aggregates, value objects, events, repository interfaces
├── Application/               commands, queries, handlers, listeners, DTOs
├── Infrastructure/            repositories, migrations (Persistence/Migrations)
├── Presentation/              Api/, Web/, Cli/
└── Tests/                     Domain/, Application/, Integration/
```

| Layer | May use | Must not use |
|---|---|---|
| Domain | PHP, zana/ddd | everything else |
| Application | its domain, other modules' contracts, ddd, mediator, validation, serialization attributes, PSR log and events | infrastructure, presentation, HTTP, the database |
| Infrastructure | domain, application, any infrastructure package | presentation |
| Presentation | application, HTTP, routing, console, mediator, serialization | the domain, infrastructure |

Between modules, only `Contracts` may be referenced, and only from a module
that declares the other as a dependency. These rules are not a convention
you have to remember: an architecture test enforces them
([section 6](#6-testing-by-layer)).

## 2. A feature, from the inside out

### The domain

The aggregate enforces the business rule and records what happened. The
patient lives in another module, so the appointment keeps only the
patient's id:

<!-- example: examples/cookbook/clinic/modules/Appointments/Domain/Appointment.php#aggregate -->
```php
/**
 * An appointment belongs to a patient by id only: the patient lives in
 * another module, so this module stores the id and nothing else.
 */
final class Appointment extends AggregateRoot
{
    private function __construct(
        AppointmentId $id,
        public readonly string $patientId,
        public readonly \DateTimeImmutable $startsAt,
        public readonly string $reason,
    ) {
        parent::__construct($id);
    }

    public static function book(AppointmentId $id, string $patientId, \DateTimeImmutable $startsAt, string $reason, \DateTimeImmutable $now): self
    {
        if ($startsAt <= $now) {
            throw new DomainException('An appointment must be in the future.');
        }

        $appointment = new self($id, $patientId, $startsAt, $reason);
        $appointment->recordEvent(new AppointmentBooked((string) $id, $patientId, $startsAt));

        return $appointment;
    }
```

### The command

Presentations turn raw input into this command with the serializer. The
mediator validates it before the handler runs:

<!-- example: examples/cookbook/clinic/modules/Appointments/Application/BookAppointment.php#command -->
```php
/**
 * Built from JSON by the serializer, validated by the mediator before the handler runs.
 */
final class BookAppointment extends Command
{
    public function __construct(
        #[SerializedName('patient_id'), NotBlank] public readonly string $patientId,
        #[SerializedName('starts_at'), ErrorMessage(invalid: 'Must be a date and time.')] public readonly \DateTimeImmutable $startsAt,
        #[NotBlank, Length(max: 200)] public readonly string $reason,
    ) {
        parent::__construct();
    }
}
```

### The handler

The handler runs the use case:

1. It looks the patient up through the Patients contract.
2. It records the appointment in a unit of work.
3. Only after the commit, it dispatches the domain events.
4. It returns a plain DTO, never the aggregate.

<!-- example: examples/cookbook/clinic/modules/Appointments/Application/BookAppointmentHandler.php#handler -->
```php
#[Handles(BookAppointment::class)]
final readonly class BookAppointmentHandler
{
    public function __construct(
        private AppointmentRepositoryInterface $appointments,
        private PatientDirectory $patients,            // the other module's contract, nothing more
        private UnitOfWorkInterface $unitOfWork,
        private EventDispatcherInterface $events,
    ) {
    }

    public function __invoke(BookAppointment $command): AppointmentView
    {
        $patient = $this->patients->find($command->patientId)
            ?? throw new AppointmentRejected(['patient_id' => 'There is no such patient.']);

        try {
            $appointment = $this->unitOfWork->transactional(function () use ($command): Appointment {
                $appointment = Appointment::book(AppointmentId::generate(), $command->patientId, $command->startsAt, $command->reason, new \DateTimeImmutable());
                $this->appointments->add($appointment);

                return $appointment;
            });
        } catch (DomainException $e) {
            throw new AppointmentRejected(['starts_at' => $e->getMessage()], $e);
        }

        foreach ($appointment->releaseEvents() as $event) {   // only after the commit
            $this->events->dispatch($event);
        }

        return new AppointmentView((string) $appointment->id(), $patient->id, $patient->name, $appointment->startsAt->format(\DATE_ATOM), $appointment->reason);
    }
}
```

### The presentation

The action only translates. JSON goes in, a command goes to the mediator,
and the result or the field errors come out:

<!-- example: examples/cookbook/clinic/modules/Appointments/Presentation/Api/BookAppointmentAction.php#action -->
```php
/**
 * POST /api/appointments {"patient_id": "…", "starts_at": "2030-01-15T09:30:00+00:00", "reason": "Check-up"}
 */
final readonly class BookAppointmentAction
{
    public function __construct(private MediatorInterface $mediator, private JsonInput $input, private ResponseFactory $responses)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $appointment = $this->mediator->send($this->input->read($request, BookAppointment::class));
        } catch (ValidationException $e) {
            throw JsonInput::invalid($e, BookAppointment::class);
        } catch (AppointmentRejected $e) {
            throw new UnprocessableEntityHttpException($e->errors, previous: $e);
        }

        return $this->responses->json($appointment, 201);
    }
}
```

The application keeps small presentation helpers shared by all modules,
here `JsonInput` (see the [complete listing](#complete-listing)). A
console command or a web page would send the same command.

### The module class

<!-- example: examples/cookbook/clinic/modules/Appointments/AppointmentsModule.php#module -->
```php
final class AppointmentsModule extends Module
{
    public function name(): string
    {
        return 'Appointments';
    }

    public function dependsOn(): array
    {
        return [PatientsModule::class];      // required to use Patients' contracts
    }

    public function register(ServiceCollectionInterface $services): void
    {
        $services
            ->scoped(AppointmentRepositoryInterface::class, DatabaseAppointmentRepository::class)
            ->scoped(BookAppointmentHandler::class)
            ->singleton(NotifyClinic::class)
            ->transient(BookAppointmentAction::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $container->get(HandlerMap::class)->register(BookAppointmentHandler::class);
        $container->get(ListenerProvider::class)->addListenerClass(NotifyClinic::class);
        $container->get(Router::class)->post('/api/appointments', BookAppointmentAction::class);
    }
}
```

If you forget a registration, the application refuses to start, and says
which service needs what:

```
[B3] Modules\Appointments\Presentation\Api\BookAppointmentAction (module:Appointments):
     constructor parameter $input: Clinic\Http\JsonInput is not registered
```

## 3. Talking to another module

A module exposes what others may know through its `Contracts` namespace:
interfaces and plain value objects, never domain classes.

<!-- example: examples/cookbook/clinic/modules/Patients/Contracts/PatientDirectory.php -->
```php
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
```

<!-- example: examples/cookbook/clinic/modules/Patients/Contracts/PatientSnapshot.php -->
```php
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
```

The module implements the contract in its application layer and registers
it (`PatientsModule` binds `PatientDirectory` to this class):

<!-- example: examples/cookbook/clinic/modules/Patients/Application/PatientDirectoryService.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Patients\Application;

use Modules\Patients\Contracts\PatientDirectory;
use Modules\Patients\Contracts\PatientSnapshot;
use Modules\Patients\Domain\PatientId;
use Modules\Patients\Domain\PatientRepositoryInterface;

/**
 * The implementation of the module's public contract. Other modules never see this class.
 */
final readonly class PatientDirectoryService implements PatientDirectory
{
    public function __construct(private PatientRepositoryInterface $patients)
    {
    }

    public function find(string $patientId): ?PatientSnapshot
    {
        if (!PatientId::isValidValue($patientId)) {
            return null;
        }
        $patient = $this->patients->find(PatientId::fromString($patientId));

        return $patient === null ? null : new PatientSnapshot((string) $patient->id(), $patient->name);
    }
}
```

- **Declare the dependency.** The other module declares it with
  `dependsOn()`, so it is booted later and cannot be enabled alone.
- **Keep the ids, not the tables.** Store the other module's ids without a
  foreign key, and look records up through the contract. Each module owns
  its tables.
- **Keep the dependencies acyclic.** If two modules need each other, the
  boundary is in the wrong place.

## 4. Events

Domain events let modules and side effects react without the core use case
knowing them. Listeners are ordinary classes, registered in `boot()`:

<!-- example: examples/cookbook/clinic/modules/Appointments/Application/NotifyClinic.php#listener -->
```php
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
```

Dispatch after the unit of work commits (as the handler above does), so a
rolled-back booking never sends a notification.

## 5. Persistence

Each module owns its tables. Its migrations live in
`Infrastructure/Persistence/Migrations`, run with `php bin/zana migrate`,
and its repositories implement the domain's interfaces with
[zana/database](../guides/database.md):

<!-- example: examples/cookbook/clinic/modules/Appointments/Infrastructure/Persistence/Migrations/2026_01_01_000001_create_appointments_table.php -->
```php
<?php

declare(strict_types=1);

use Zana\Database\Migration\Migration;
use Zana\Database\Schema\Blueprint;
use Zana\Database\Schema\Schema;

return new class () extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('appointments', static function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('patient_id');
            $t->dateTime('starts_at');
            $t->string('reason', 200);
            $t->index('patient_id');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('appointments');
    }
};
```

## 6. Testing by layer

**The domain** is plain PHP, so its tests need nothing else:

<!-- example: examples/cookbook/clinic/modules/Appointments/Tests/Domain/AppointmentTest.php#domain-test -->
```php
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
```

**The application layer** is tested with fakes, including a fake of the
other module's contract. That proves the module needs nothing more from it:

<!-- example: examples/cookbook/clinic/modules/Appointments/Tests/Application/BookAppointmentHandlerTest.php#application-test -->
```php
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
```

**Integration tests** run the whole application with a fresh database per
test, over HTTP:

<!-- example: examples/cookbook/clinic/tests/ClinicTest.php#integration-test -->
```php
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
```

**The architecture test** applies the dependency rules to every module,
including the ones you add later:

<!-- example: examples/cookbook/clinic/tests/ArchitectureTest.php#architecture-test -->
```php
final class ArchitectureTest extends TestCase
{
    use ArchitectureAssertions;

    public function testEveryModuleFollowsTheDependencyRules(): void
    {
        $rules = ModuleRules::for(dirname(__DIR__) . '/modules');

        $this->assertSame(['Appointments', 'Patients'], $rules->modules());
        self::assertModuleArchitecture($rules);
    }
}
```

## 7. Adding a module

1. Generate it: `php bin/zana make:module Billing` (see [maker](../guides/maker.md)).
   It creates the layers and enables the module in `config/modules.php`.
2. Model the domain first: aggregates, value objects, events, repository
   interfaces.
3. Add use cases with `make:feature`. They are wired into the module
   automatically.
4. Need another module? Declare it in `dependsOn()`, and use its
   `Contracts` only.
5. Add persistence (a migration and a repository) and presentations.
6. Run the tests: the architecture test checks the new module from the
   first commit.

## Complete listing

The rest of the clinic application, for reference.

### Composition root and configuration

<!-- example: examples/cookbook/clinic/src/Bootstrap.php -->
```php
<?php

declare(strict_types=1);

namespace Clinic;

use Clinic\Http\JsonInput;
use Clinic\Http\ValidationBehavior;
use Zana\Console\ConsoleServiceProvider;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Application;
use Zana\Core\ApplicationBuilder;
use Zana\Database\DatabaseServiceProvider;
use Zana\Events\EventsServiceProvider;
use Zana\Http\HttpServiceProvider;
use Zana\Logging\LoggingServiceProvider;
use Zana\Mediator\MediatorServiceProvider;
use Zana\Routing\RoutingServiceProvider;
use Zana\Serialization\SerializationServiceProvider;
use Zana\Validation\ValidationServiceProvider;

final class Bootstrap
{
    public static function builder(string $basePath): ApplicationBuilder
    {
        return Application::configure($basePath)
            ->withProviders(
                LoggingServiceProvider::class,
                EventsServiceProvider::class,
                HttpServiceProvider::class,
                RoutingServiceProvider::class,
                ConsoleServiceProvider::class,
                DatabaseServiceProvider::class,
                MediatorServiceProvider::class,
                ValidationServiceProvider::class,
                SerializationServiceProvider::class,
            )
            ->withServices(static fn (ServiceCollectionInterface $s) => $s
                ->singleton(ValidationBehavior::class)
                ->singleton(JsonInput::class));
    }
}
```

<!-- example: examples/cookbook/clinic/src/Http/ValidationBehavior.php -->
```php
<?php

declare(strict_types=1);

namespace Clinic\Http;

use Zana\Mediator\PipelineBehavior;
use Zana\Validation\Validator;

/**
 * Validates every command and query against its constraints before the handler runs.
 */
final readonly class ValidationBehavior implements PipelineBehavior
{
    public function __construct(private Validator $validator)
    {
    }

    public function handle(object $message, \Closure $next): mixed
    {
        $this->validator->assertValid($message);

        return $next($message);
    }
}
```

<!-- example: examples/cookbook/clinic/src/Http/JsonInput.php -->
```php
<?php

declare(strict_types=1);

namespace Clinic\Http;

use Psr\Http\Message\ServerRequestInterface;
use Zana\Http\Exception\BadRequestHttpException;
use Zana\Http\Exception\UnprocessableEntityHttpException;
use Zana\Serialization\Attribute\SerializedName;
use Zana\Serialization\DenormalizationException;
use Zana\Serialization\Serializer;
use Zana\Validation\ValidationException;

/**
 * Turns a JSON body into a typed message, and input failures into a 422
 * with field errors under the public (JSON) names.
 */
final readonly class JsonInput
{
    public function __construct(private Serializer $serializer)
    {
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function read(ServerRequestInterface $request, string $class): object
    {
        $data = json_decode((string) $request->getBody(), true);
        if (!is_array($data)) {
            throw new BadRequestHttpException('The request body must be a JSON object.');
        }

        try {
            return $this->serializer->denormalize($data, $class);
        } catch (DenormalizationException $e) {
            throw new UnprocessableEntityHttpException($e->errors(), previous: $e);
        }
    }

    /**
     * A 422 for a failed validation, with each property renamed to its JSON name.
     *
     * @param class-string $class
     */
    public static function invalid(ValidationException $e, string $class): UnprocessableEntityHttpException
    {
        $errors = [];
        foreach ($e->violations->toArray() as $path => $messages) {
            $attribute = property_exists($class, $path) ? (new \ReflectionProperty($class, $path))->getAttributes(SerializedName::class)[0] ?? null : null;
            $errors[$attribute?->newInstance()->name ?? $path] = $messages;
        }

        return new UnprocessableEntityHttpException($errors, previous: $e);
    }
}
```

<!-- example: examples/cookbook/clinic/config/modules.php -->
```php
<?php

declare(strict_types=1);

return [
    Modules\Patients\PatientsModule::class,
    Modules\Appointments\AppointmentsModule::class,
];
```

<!-- example: examples/cookbook/clinic/config/database.php -->
```php
<?php

declare(strict_types=1);

return [
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => ['driver' => 'sqlite', 'database' => 'var/clinic.sqlite'],
    ],
];
```

<!-- example: examples/cookbook/clinic/config/mediator.php -->
```php
<?php

declare(strict_types=1);

return [
    'behaviors' => [Clinic\Http\ValidationBehavior::class],
];
```

<!-- example: examples/cookbook/clinic/config/testing/logging.php -->
```php
<?php

declare(strict_types=1);

// Tests read log records from memory.
return [
    'default' => 'app',
    'channels' => ['app' => ['handler' => 'array']],
];
```

### Patients

<!-- example: examples/cookbook/clinic/modules/Patients/PatientsModule.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Patients;

use Modules\Patients\Application\PatientDirectoryService;
use Modules\Patients\Application\RegisterPatientHandler;
use Modules\Patients\Contracts\PatientDirectory;
use Modules\Patients\Domain\PatientRepositoryInterface;
use Modules\Patients\Infrastructure\DatabasePatientRepository;
use Modules\Patients\Presentation\Api\RegisterPatientAction;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Module;
use Zana\Mediator\HandlerMap;
use Zana\Routing\Router;

final class PatientsModule extends Module
{
    public function name(): string
    {
        return 'Patients';
    }

    public function register(ServiceCollectionInterface $services): void
    {
        $services
            ->scoped(PatientRepositoryInterface::class, DatabasePatientRepository::class)
            ->scoped(PatientDirectory::class, PatientDirectoryService::class)   // the public contract
            ->scoped(RegisterPatientHandler::class)
            ->transient(RegisterPatientAction::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $container->get(HandlerMap::class)->register(RegisterPatientHandler::class);
        $container->get(Router::class)->post('/api/patients', RegisterPatientAction::class);
    }
}
```

<!-- example: examples/cookbook/clinic/modules/Patients/Domain/Patient.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Patients\Domain;

use Zana\Ddd\Domain\AggregateRoot;
use Zana\Ddd\Domain\DomainException;

final class Patient extends AggregateRoot
{
    private function __construct(PatientId $id, public readonly string $name)
    {
        parent::__construct($id);
    }

    public static function register(PatientId $id, string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new DomainException('A patient needs a name.');
        }

        return new self($id, $name);
    }

    public static function restore(PatientId $id, string $name): self
    {
        return new self($id, $name);
    }

    public function id(): PatientId
    {
        assert($this->id instanceof PatientId);

        return $this->id;
    }
}
```

<!-- example: examples/cookbook/clinic/modules/Patients/Domain/PatientId.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Patients\Domain;

use Zana\Ddd\Domain\Identity;

final readonly class PatientId extends Identity
{
}
```

<!-- example: examples/cookbook/clinic/modules/Patients/Domain/PatientRepositoryInterface.php -->
```php
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
```

<!-- example: examples/cookbook/clinic/modules/Patients/Application/RegisterPatient.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Patients\Application;

use Zana\Ddd\Application\Command;
use Zana\Validation\Constraint\Length;
use Zana\Validation\Constraint\NotBlank;

final class RegisterPatient extends Command
{
    public function __construct(
        #[NotBlank, Length(max: 100)] public readonly string $name,
    ) {
        parent::__construct();
    }
}
```

<!-- example: examples/cookbook/clinic/modules/Patients/Application/RegisterPatientHandler.php -->
```php
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
```

<!-- example: examples/cookbook/clinic/modules/Patients/Infrastructure/DatabasePatientRepository.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Patients\Infrastructure;

use Modules\Patients\Domain\Patient;
use Modules\Patients\Domain\PatientId;
use Modules\Patients\Domain\PatientRepositoryInterface;
use Zana\Database\ConnectionInterface;
use Zana\Ddd\Domain\AggregateRoot;
use Zana\Ddd\Domain\EntityNotFoundException;

final readonly class DatabasePatientRepository implements PatientRepositoryInterface
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function find(mixed $id): ?Patient
    {
        $row = $this->db->table('patients')->where('id', (string) $id)->first();

        return $row === null ? null : Patient::restore(PatientId::fromString((string) $row['id']), (string) $row['name']);
    }

    public function get(mixed $id): Patient
    {
        return $this->find($id) ?? throw new EntityNotFoundException("There is no patient {$id}.");
    }

    public function add(AggregateRoot $aggregate): void
    {
        assert($aggregate instanceof Patient);
        $this->db->table('patients')->insert(['id' => (string) $aggregate->id(), 'name' => $aggregate->name]);
    }

    public function remove(AggregateRoot $aggregate): void
    {
        assert($aggregate instanceof Patient);
        $this->db->table('patients')->where('id', (string) $aggregate->id())->delete();
    }
}
```

<!-- example: examples/cookbook/clinic/modules/Patients/Infrastructure/Persistence/Migrations/2026_01_01_000000_create_patients_table.php -->
```php
<?php

declare(strict_types=1);

use Zana\Database\Migration\Migration;
use Zana\Database\Schema\Blueprint;
use Zana\Database\Schema\Schema;

return new class () extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('patients', static function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name', 100);
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('patients');
    }
};
```

<!-- example: examples/cookbook/clinic/modules/Patients/Presentation/Api/RegisterPatientAction.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Patients\Presentation\Api;

use Clinic\Http\JsonInput;
use Modules\Patients\Application\RegisterPatient;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zana\Http\ResponseFactory;
use Zana\Mediator\MediatorInterface;
use Zana\Validation\ValidationException;

/**
 * POST /api/patients {"name": "Ada Lovelace"}
 */
final readonly class RegisterPatientAction
{
    public function __construct(private MediatorInterface $mediator, private JsonInput $input, private ResponseFactory $responses)
    {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $patient = $this->mediator->send($this->input->read($request, RegisterPatient::class));
        } catch (ValidationException $e) {
            throw JsonInput::invalid($e, RegisterPatient::class);
        }

        return $this->responses->json($patient, 201);
    }
}
```

### Appointments

<!-- example: examples/cookbook/clinic/modules/Appointments/Domain/AppointmentId.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Appointments\Domain;

use Zana\Ddd\Domain\Identity;

final readonly class AppointmentId extends Identity
{
}
```

<!-- example: examples/cookbook/clinic/modules/Appointments/Domain/AppointmentBooked.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Appointments\Domain;

use Zana\Ddd\Domain\DomainEvent;

final class AppointmentBooked extends DomainEvent
{
    public function __construct(public readonly string $appointmentId, public readonly string $patientId, public readonly \DateTimeImmutable $startsAt)
    {
        parent::__construct();
    }
}
```

<!-- example: examples/cookbook/clinic/modules/Appointments/Domain/AppointmentRepositoryInterface.php -->
```php
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
```

<!-- example: examples/cookbook/clinic/modules/Appointments/Application/AppointmentView.php -->
```php
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
```

<!-- example: examples/cookbook/clinic/modules/Appointments/Application/AppointmentRejected.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Appointments\Application;

/**
 * The booking was refused: an unknown patient or a domain rule. Carries field errors.
 */
final class AppointmentRejected extends \RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(public readonly array $errors, ?\Throwable $previous = null)
    {
        parent::__construct(implode(' ', $errors), 0, $previous);
    }
}
```

<!-- example: examples/cookbook/clinic/modules/Appointments/Infrastructure/DatabaseAppointmentRepository.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Appointments\Infrastructure;

use Modules\Appointments\Domain\Appointment;
use Modules\Appointments\Domain\AppointmentId;
use Modules\Appointments\Domain\AppointmentRepositoryInterface;
use Zana\Database\ConnectionInterface;
use Zana\Ddd\Domain\AggregateRoot;
use Zana\Ddd\Domain\EntityNotFoundException;

final readonly class DatabaseAppointmentRepository implements AppointmentRepositoryInterface
{
    public function __construct(private ConnectionInterface $db)
    {
    }

    public function find(mixed $id): ?Appointment
    {
        $row = $this->db->table('appointments')->where('id', (string) $id)->first();

        return $row === null ? null : Appointment::restore(
            AppointmentId::fromString((string) $row['id']),
            (string) $row['patient_id'],
            new \DateTimeImmutable((string) $row['starts_at'], new \DateTimeZone('UTC')),
            (string) $row['reason'],
        );
    }

    public function get(mixed $id): Appointment
    {
        return $this->find($id) ?? throw new EntityNotFoundException("There is no appointment {$id}.");
    }

    public function add(AggregateRoot $aggregate): void
    {
        assert($aggregate instanceof Appointment);
        $this->db->table('appointments')->insert([
            'id' => (string) $aggregate->id(),
            'patient_id' => $aggregate->patientId,          // no foreign key: patients belong to another module
            'starts_at' => $aggregate->startsAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'reason' => $aggregate->reason,
        ]);
    }

    public function remove(AggregateRoot $aggregate): void
    {
        assert($aggregate instanceof Appointment);
        $this->db->table('appointments')->where('id', (string) $aggregate->id())->delete();
    }
}
```

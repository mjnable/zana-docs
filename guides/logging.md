# Logging

`mj-zana/logging` is a PSR-3 logger with handlers, formatters and a shared
context. Anything that accepts a `Psr\Log\LoggerInterface` works with it.

```
composer require mj-zana/logging
```

## A logger

A logger has a channel name, handlers, each with a minimum level, and
processors that enrich every record:

<!-- example: examples/guides/logging.php#logger -->
```php
$context = new LogContext();                       // values added to every record, e.g. correlation_id
$memory = new ArrayHandler();
$logger = new Logger('app', [
    new StreamHandler('php://stdout', LogLevel::Info, new LineFormatter()),
    $memory,
], [new ContextProcessor($context)], $clock);

$context->set('correlation_id', 'req-42');
$logger->info('Patient {id} registered', ['id' => 7]);
$logger->debug('Not written to stdout: below Info');
$logger->warning('BMI {bmi} is high', ['bmi' => 31.2]);
```

`{placeholders}` are filled from the context, and the context is kept on the
record. The `ArrayHandler` keeps records in memory, which suits tests:

<!-- example: examples/guides/logging.php#records -->
```php
foreach ($memory->records() as $record) {
    echo strtoupper($record->level->value), ' | ', $record->message, ' | ', json_encode($record->extra), "\n";
}
echo var_export($memory->hasRecord(LogLevel::Warning, 'is high'), true), "\n";
```

<!-- example: examples/guides/logging.out -->
```text
[2026-10-25T09:00:00+00:00] app.INFO: Patient 7 registered {"id":7} {"correlation_id":"req-42"}
[2026-10-25T09:00:00+00:00] app.WARNING: BMI 31.2 is high {"bmi":31.2} {"correlation_id":"req-42"}
INFO | Patient 7 registered | {"correlation_id":"req-42"}
DEBUG | Not written to stdout: below Info | {"correlation_id":"req-42"}
WARNING | BMI 31.2 is high | {"correlation_id":"req-42"}
true
```

## In an application

Register `LoggingServiceProvider`, inject `LoggerInterface`, and configure
channels in `config/logging.php`:

```php
return [
    'default' => 'app',
    'channels' => [
        'app' => ['handler' => 'stream', 'path' => 'var/log/app.log', 'level' => 'info'],   // or formatter: json
        'audit' => ['handler' => 'stream', 'path' => 'php://stderr', 'formatter' => 'json'],
    ],
];
```

- **Handlers:**
  - `stream`: a file or `php://` stream, with relative paths resolved
    against the base path;
  - `array`: in memory, for tests, e.g. in `config/testing/logging.php`;
  - `null`: discards everything.
- **Default:** without any configuration, warnings and above go to
  `php://stderr`.
- **Other channels:** `LogManager::channel('audit')` returns a channel's
  logger.
- **Correlation ids:** the HTTP and console kernels put each request's or
  command's id into the `LogContext`. So every line carries
  `correlation_id` without any code of yours. A valid `X-Correlation-Id`
  header, or the `ZANA_CORRELATION_ID` variable for commands, is reused, so
  logs can be followed across services.

## Rules worth knowing

- **Records are normalised for output.** Exceptions become their class,
  message, code and file:line, previous ones included (no stack trace).
  Dates become ISO strings. `JsonSerializable`
  objects are serialised, other objects and resources become a short
  description, and deep nesting stops at a maximum depth. Logging never
  fails because of an odd context value.
- **Levels are the eight PSR-3 levels.** Unknown level names throw an
  exception that implements PSR-3's `InvalidArgumentException`.

Reference: the [logging README](https://github.com/mjnable/zana-logging).

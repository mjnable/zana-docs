# Queue

`mj-zana/queue` runs work in the background. Jobs go onto a queue, and a
worker processes them, each in its own container scope, with retries and
failure storage.

```
composer require mj-zana/queue
```

## A job

A job is a serializable class with a `handle()` method. Its dependencies are
injected when it runs, so they are never serialized:

<!-- example: examples/guides/queue.php#job -->
```php
#[JobOptions(queue: 'mail', tries: 3, backoff: [10, 60])]
final readonly class SendWelcomeMail
{
    public function __construct(public string $email)   // serialized onto the queue
    {
    }

    public function handle(Mailer $mailer): void        // injected from the job's own scope
    {
        $mailer->send($this->email);
    }
}
```

## Dispatching and working

<!-- example: examples/guides/queue.php#work -->
```php
// In an application: config/queue.php picks the driver (sync, memory or database),
// and `php bin/zana queue:work mail` runs the worker.
$queue = new InMemoryQueue($clock);
$container = (new ServiceCollection())->singleton(Mailer::class)->build();
$worker = new Worker($queue, $container);

(new Dispatcher($queue))->dispatch(new SendWelcomeMail('ada@example.com'));   // goes to "mail"

$mailer = $container->get(Mailer::class);
foreach ([0, 5, 5, 60] as $wait) {                  // tries 3, waiting 10s then 60s between them
    $now += $wait;
    $processed = $worker->runNext('mail');
    echo "+{$wait}s: ", $processed ? "attempt {$mailer->attempts}" : 'not due yet', "\n";
}
echo 'sent to: ', implode(', ', $mailer->sent), '; ', $queue->size('mail'), ' waiting, ', count($queue->failed()), " failed\n";
```

The mailer fails twice. The job is retried after 10 seconds, then after 60,
and the third try succeeds:

<!-- example: examples/guides/queue.out -->
```text
+0s: attempt 1
+5s: not due yet
+5s: attempt 2
+60s: attempt 3
sent to: ada@example.com; 0 waiting, 0 failed
```

## In an application

Register `QueueServiceProvider` (with `ConsoleServiceProvider`), inject
`Dispatcher`, and configure `config/queue.php`:

```php
return [
    'default' => 'database',    // sync (the default), memory or database
    'connection' => 'main',     // database connection; the default one when omitted
    'retry_after' => 90,        // seconds before an abandoned reservation is taken again
];
```

```
php bin/zana queue:table            # writes the migration for the database queue tables
php bin/zana migrate
php bin/zana queue:work mail --sleep=3
php bin/zana queue:work --max-jobs=100 --stop-when-empty
php bin/zana queue:failed
php bin/zana queue:retry 12 13      # or: queue:retry all
```

- **`sync`:** runs each job inside `dispatch()`, once, and rethrows its
  failure. Handy in development and tests.
- **`database`:** reserves jobs with a conditional update inside a
  transaction, so two workers never take the same job.

## Rules worth knowing

- **Retries:** `JobOptions(tries:, backoff:)` controls them. Before attempt
  n+1 the worker waits `backoff[n-1]` seconds; the last value repeats.
- **After the last try,** the job moves to the failed jobs, and the worker
  logs an `error`. Earlier failures log a `warning`.
- **One scope per job.** Scoped services are fresh for each job and
  disposed afterwards. The scope carries the attribute `job_id`.
- **Payloads are read back narrowly.** Only the job class and the classes
  of its typed properties are accepted. An object in an untyped or array
  property makes the job fail.
- **Closures and anonymous classes cannot be queued.**

Reference: the [queue README](https://github.com/mjnable/zana-queue).

# Scheduler

`mj-zana/scheduler` (2.2) runs recurring tasks from a single cron entry:
publishing posts at their scheduled time, cleaning old sessions, sending a
weekly report.

```
composer require mj-zana/scheduler
```

## Cron expressions

The five fields are minute, hour, day of month, month and day of week.
Lists, ranges, steps and names (`mon`, `jan`) work as in cron.

<!-- example: examples/guides/scheduler.php#cron -->
```php
$weekdayMornings = new CronExpression('30 7 * * mon-fri');
echo 'Due on Monday 07:30: ', var_export($weekdayMornings->isDue(new DateTimeImmutable('2026-10-26 07:30')), true), "\n";
echo 'Next after Friday 08:00: ', $weekdayMornings->nextRun(new DateTimeImmutable('2026-10-30 08:00'))->format('D Y-m-d H:i'), "\n";
```

## Defining the schedule

<!-- example: examples/guides/scheduler.php#schedule -->
```php
// In a module's boot(): $schedule = $container->get(Schedule::class);
$schedule = new Schedule();
$schedule->call(static fn () => null)->everyMinute()->name('publish due posts')->withoutOverlapping();
$schedule->call(static fn () => null)->dailyAt('03:00')->name('clean old sessions');
$schedule->call(static fn () => null)->cron('0 7 * * mon')->name('weekly report');

$at = new DateTimeImmutable('2026-10-26 03:00');   // a Monday
foreach ($schedule->tasks() as $task) {
    printf("%-20s %-12s due at 03:00: %s\n", $task->describe(), $task->expression(), $task->isDue($at) ? 'yes' : 'no');
}
```

<!-- example: examples/guides/scheduler.out -->
```text
Due on Monday 07:30: true
Next after Friday 08:00: Mon 2026-11-02 07:30
publish due posts    * * * * *    due at 03:00: yes
clean old sessions   0 3 * * *    due at 03:00: yes
weekly report        0 7 * * mon  due at 03:00: no
```

`call()` takes a closure, a service class with `__invoke()`, or a
`[class, method]` pair; services are resolved from the container in a
scope of their own, as for a console command. Frequencies:
`everyMinute()`, `everyFiveMinutes()`, `everyFifteenMinutes()`, `hourly()`,
`daily()`, `dailyAt('03:00')`, `weekly()`, `monthly()` and `cron(...)`.
`timezone('Africa/Kinshasa')` evaluates a task in another time zone than
the application's.

Rules worth knowing:

- **One failure does not stop the others.** Each task runs on its own; a
  failure is logged and reported in the command's output and exit code.
- **`withoutOverlapping()`** takes a cache lock for the task, so a slow run
  is not started again by the next minute's cron. The lock expires after
  an hour by default, in case a run dies.
- **Tasks run in sequence.** Keep them short, and send long work to the
  [queue](queue.md).

## In an application

Register `SchedulerServiceProvider`, define tasks in your modules' `boot()`
with the `Schedule` service, and add one cron entry on the server:

```
* * * * * cd /var/www/acme && php bin/console schedule:run >> /dev/null 2>&1
```

`php bin/console schedule:list` shows every task with its next run.

<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Scheduler\CronExpression;
use Zana\Scheduler\Schedule;

// region: cron
$weekdayMornings = new CronExpression('30 7 * * mon-fri');
echo 'Due on Monday 07:30: ', var_export($weekdayMornings->isDue(new DateTimeImmutable('2026-10-26 07:30')), true), "\n";
echo 'Next after Friday 08:00: ', $weekdayMornings->nextRun(new DateTimeImmutable('2026-10-30 08:00'))->format('D Y-m-d H:i'), "\n";
// endregion: cron

// region: schedule
// In a module's boot(): $schedule = $container->get(Schedule::class);
$schedule = new Schedule();
$schedule->call(static fn () => null)->everyMinute()->name('publish due posts')->withoutOverlapping();
$schedule->call(static fn () => null)->dailyAt('03:00')->name('clean old sessions');
$schedule->call(static fn () => null)->cron('0 7 * * mon')->name('weekly report');

$at = new DateTimeImmutable('2026-10-26 03:00');   // a Monday
foreach ($schedule->tasks() as $task) {
    printf("%-20s %-12s due at 03:00: %s\n", $task->describe(), $task->expression(), $task->isDue($at) ? 'yes' : 'no');
}
// endregion: schedule

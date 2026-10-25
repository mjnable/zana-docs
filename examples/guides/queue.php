<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Container\ServiceCollection;
use Zana\Queue\Dispatcher;
use Zana\Queue\InMemoryQueue;
use Zana\Queue\JobOptions;
use Zana\Queue\Worker;

final class Mailer
{
    public int $attempts = 0;

    /** @var list<string> */
    public array $sent = [];

    public function send(string $to): void
    {
        if (++$this->attempts < 3) {
            throw new RuntimeException('mail server unavailable');
        }
        $this->sent[] = $to;
    }
}

// region: job
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
// endregion: job

$now = 1_800_000_000;
$clock = static function () use (&$now): int {
    return $now;
};

// region: work
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
// endregion: work

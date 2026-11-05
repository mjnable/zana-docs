<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Database\ConnectionFactory;
use Zana\Database\ConnectionInterface;
use Zana\Ddd\Domain\AggregateRoot;
use Zana\Ddd\Domain\DomainEvent;
use Zana\Ddd\Domain\Uuid;
use Zana\Events\EventDispatcher;
use Zana\Events\ListenerProvider;
use Zana\Outbox\Inbox;
use Zana\Outbox\Outbox;
use Zana\Outbox\OutboxRelay;
use Zana\Outbox\OutboxTablesMigration;

final class TicketsIssued extends DomainEvent
{
    public function __construct(public readonly string $orderId, public readonly int $tickets)
    {
        parent::__construct();
    }
}

final class Order extends AggregateRoot
{
    public static function pay(int $tickets): self
    {
        $order = new self(Uuid::v7());
        $order->recordEvent(new TicketsIssued((string) $order->getId(), $tickets));

        return $order;
    }
}

$db = ConnectionFactory::create(['driver' => 'sqlite', 'database' => ':memory:']);
(new OutboxTablesMigration())->up($db->schema());   // php bin/zana outbox:install, then migrate

// region: listener
// An ordinary event listener. It can see a message twice (at least once), so the inbox makes
// sending the mail happen once per event.
$inbox = new Inbox($db);
$mailServerUp = false;
$listeners = (new ListenerProvider())->listen(TicketsIssued::class, static function (TicketsIssued $event) use ($inbox, &$mailServerUp): void {
    $inbox->once('tickets.mail', $event->getEventId(), static function () use ($event, $mailServerUp): void {
        if (!$mailServerUp) {
            throw new RuntimeException('The mail server is down.');
        }
        echo "  mail: {$event->tickets} tickets\n";
    });
});
// endregion: listener

$relay = new OutboxRelay($db, new EventDispatcher($listeners), tries: 3, backoff: [0]);
$outbox = new Outbox($db, $relay);   // in an application, resolve Outbox from the scope

// region: record
// The order and its event are saved in one transaction; the event is relayed after the commit.
$outbox->transactional(static function (ConnectionInterface $tx) use ($outbox): void {
    $order = Order::pay(2);
    // ... save the order with $tx ...
    $outbox->record($order);
});

try {
    $outbox->transactional(static function () use ($outbox): void {
        $outbox->record(Order::pay(5));
        throw new RuntimeException('The payment provider refused.');
    });
} catch (RuntimeException) {
    // Rolled back: no order, and no message.
}
// endregion: record

// region: relay
// php bin/zana outbox:relay, on a schedule: delivers what is due; a failed message waits backoff[attempt - 1]
// seconds (here 0) and is tried again.
$report = static function (string $when) use ($relay): void {
    $counts = $relay->counts();
    echo "{$when}: {$counts['pending']} pending, {$counts['dispatched']} delivered, {$counts['failed']} failed\n";
};
$report('After the commit');
$mailServerUp = true;
$relay->relay();
$report('At the next run');
$relay->relay();   // nothing is due: delivered messages are not delivered again
// endregion: relay

echo 'Mails recorded by the inbox: ', $db->table('inbox_messages')->count(), "\n";

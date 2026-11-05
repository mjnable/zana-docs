# Outbox

`mj-zana/outbox` (2.13) delivers events reliably: it saves them in the same
transaction as the change that caused them, then dispatches them through the
event dispatcher, at least once, with retries. Its inbox lets a consumer
handle each message once.

```
composer require mj-zana/outbox
```

Without it, an application that saves an order and then dispatches
`TicketsIssued` can lose the event: the process stops after the commit, or
the mail listener fails. Or it can send the tickets for an order that was
rolled back. The outbox closes both gaps.

## Recording events

Record the messages inside the transaction of the change. A rollback leaves
none behind, and `transactional()` relays them once the outermost
transaction commits:

<!-- example: examples/guides/outbox.php#record -->
```php
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
```

- **`record(...$aggregates)`** saves the domain events the aggregates
  release. **`add(...$messages)`** saves any message object.
- **Outside a transaction,** both throw an `OutboxException`: a message
  could then exist without its change.
- **Your own transaction:** when the code calls `$db->transaction()`
  itself, the messages are relayed when the scope ends, or by the scheduled
  relay.
- **Ids:** a `DomainEvent` keeps its event id; other messages get a UUID
  v7.
- **Payloads** are stored with `serialize()`, as base64. When one is read
  back, only the message class and the classes its typed properties declare
  are allowed. Closures and anonymous classes are refused.

`Outbox` is scoped, so each request, job or command has its own.

## Delivery and the inbox

The relay dispatches each message through the PSR-14 dispatcher, so the
listeners are ordinary [event listeners](events.md). A relay claims a
message before delivering it, so two relays never deliver it at the same
time. Delivery is at least once: a process can stop after a listener ran
and before the message is marked delivered. A listener with side effects
uses the inbox, which runs its handler once per consumer and message:

<!-- example: examples/guides/outbox.php#listener -->
```php
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
```

The inbox records the message in the handler's transaction. A handler that
throws is not recorded, so the next delivery tries it again.

## The relay

A listener that throws makes its message wait `backoff[attempt - 1]`
seconds; the last value repeats. After `tries` attempts, or when its payload
cannot be read, the message is marked failed with its error. Each retry logs
a `warning`, and a failure an `error`.

<!-- example: examples/guides/outbox.php#relay -->
```php
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
```

The mail server is down when the order commits, so the first delivery
fails. The next run delivers it, and the rolled-back order sent nothing:

<!-- example: examples/guides/outbox.out -->
```text
After the commit: 1 pending, 0 delivered, 0 failed
  mail: 2 tickets
At the next run: 0 pending, 1 delivered, 0 failed
Mails recorded by the inbox: 1
```

Run the relay from the [scheduler](scheduler.md) or as a long-running
process:

```
php bin/zana outbox:relay                    # deliver what is due, then stop
php bin/zana outbox:relay --loop --sleep=5
php bin/zana outbox:retry 01J9...            # make a failed message due again
php bin/zana outbox:retry all
```

## Configuration

Register `OutboxServiceProvider` with `DatabaseServiceProvider` and
`EventsServiceProvider`, then create the tables:

```
php bin/zana outbox:install
php bin/zana migrate
```

```php
// config/outbox.php
return [
    'tries' => 5,                     // attempts before a message is marked failed
    'backoff' => [10, 60, 300, 1800], // seconds before each retry
    'claim_seconds' => 60,            // how long a relay holds a message it delivers
    'warn_after' => 300,              // check warns when a message waits longer
];
```

`check` reports an error when the outbox table cannot be read. It warns
when a message has waited longer than `warn_after`, and when messages have
failed. `inspect outbox` shows the pending, failed and delivered counts and
the age of the oldest pending message.

The outbox is tested on SQLite, MySQL and PostgreSQL.

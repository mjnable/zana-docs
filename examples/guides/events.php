<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Container\ServiceCollection;
use Zana\Events\AsListener;
use Zana\Events\EventDispatcher;
use Zana\Events\ListenerProvider;
use Zana\Events\StoppableEvent;

final class OrderPlaced extends StoppableEvent
{
    public function __construct(public readonly string $orderId, public readonly int $amount)
    {
    }
}

// region: listener
#[AsListener(priority: 10)]                        // the event comes from the parameter type
final class FraudCheck
{
    public function __invoke(OrderPlaced $event): void
    {
        echo "fraud check for {$event->orderId}\n";
        if ($event->amount > 10_000) {
            echo "  blocked: amount too high\n";
            $event->stopPropagation();             // later listeners are skipped
        }
    }
}
// endregion: listener

// region: dispatch
$container = (new ServiceCollection())->singleton(FraudCheck::class)->build();
$listeners = new ListenerProvider($container);     // listener classes are resolved from it, when first needed
$listeners
    ->listen(OrderPlaced::class, static function (OrderPlaced $event): void {
        echo "send confirmation for {$event->orderId}\n";
    })
    ->addListenerClass(FraudCheck::class);         // priority 10 runs before priority 0

$events = new EventDispatcher($listeners);

$events->dispatch(new OrderPlaced('A-1', 250));
$events->dispatch(new OrderPlaced('A-2', 50_000));
// endregion: dispatch

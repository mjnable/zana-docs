# Events

`mj-zana/events` is a PSR-14 event dispatcher. Listener classes are created
only when an event they handle is dispatched.

```
composer require mj-zana/events
```

## Listeners

A listener is any callable, or a class with `__invoke()`. With
`#[AsListener]`, the event is read from the parameter type:

<!-- example: examples/guides/events.php#listener -->
```php
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
```

## Dispatching

<!-- example: examples/guides/events.php#dispatch -->
```php
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
```

<!-- example: examples/guides/events.out -->
```text
fraud check for A-1
send confirmation for A-1
fraud check for A-2
  blocked: amount too high
```

## In an application

- **Wiring:** register `EventsServiceProvider` and inject
  `Psr\EventDispatcher\EventDispatcherInterface`. Modules add their
  listeners in `boot()`:
  `$container->get(ListenerProvider::class)->addListenerClass(...)`, and
  register the listener classes as services.
- **Domain events:** aggregates record them (see [DDD](ddd.md)). Dispatch
  them *after* the unit of work commits, so listeners never react to
  changes that were rolled back:

  ```php
  foreach ($order->releaseEvents() as $event) {
      $this->events->dispatch($event);
  }
  ```

## Rules worth knowing

- **Order:** higher priority first, then registration order.
- **Matching:** a listener for a class or interface receives events of that
  class, its subclasses and its implementations.
- **Exceptions are never caught.** A failing listener stops the dispatch
  and the exception reaches the caller.
- **Listener lifetime:** listener instances are reused, so register them as
  singletons or keep them stateless.
- **More options:** `EventSubscriberInterface` maps several events to
  methods, and `dispatchAll()` dispatches a list of events in order.

Reference: the [events README](https://github.com/mjnable/zana-events).

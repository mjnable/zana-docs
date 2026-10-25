# Domain-driven design

`mj-zana/ddd` provides the building blocks for a domain model: entities and
aggregate roots, value objects, typed identities, domain events, results and
specifications. It depends on nothing else, so your domain layer stays
plain PHP.

```
composer require mj-zana/ddd
```

## Value objects

A value object is immutable and equal to any other with the same values.
`ValueObject` is a `readonly` class, so subclasses must be `readonly` too:

<!-- example: examples/guides/ddd.php#value-object -->
```php
final readonly class Money extends ValueObject
{
    public function __construct(public int $cents, public string $currency)
    {
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents, $this->currency);
    }
}
```

## Aggregates, identities and events

An aggregate root is the entry point of a cluster of objects that changes
as a unit. It records domain events as things happen:

<!-- example: examples/guides/ddd.php#aggregate -->
```php
final readonly class OrderId extends Identity
{
}

final class OrderPlaced extends DomainEvent
{
    public function __construct(public readonly string $orderId, public readonly Money $total)
    {
        parent::__construct();
    }
}

final class Order extends AggregateRoot
{
    private Money $total;

    public static function place(OrderId $id, Money ...$lines): self
    {
        $order = new self($id);
        $order->total = array_reduce($lines, static fn (Money $sum, Money $line): Money => $sum->add($line), new Money(0, 'EUR'));
        $order->recordEvent(new OrderPlaced((string) $id, $order->total));

        return $order;
    }

    public function total(): Money
    {
        return $this->total;
    }
}
```

<!-- example: examples/guides/ddd.php#usage -->
```php
$id = OrderId::fromString('0190a8f0-1234-7000-8000-000000000001');
$order = Order::place($id, new Money(1250, 'EUR'), new Money(750, 'EUR'));

var_export($order->total()->equals(new Money(2000, 'EUR')));   // equal by value
echo "\n";
var_export($order->equals(Order::place(OrderId::fromString((string) $id))));   // equal by identity
echo "\n";
foreach ($order->releaseEvents() as $event) {
    echo $event::class, ' for ', $event->orderId, ': ', $event->total->cents, "\n";
}
echo count($order->releaseEvents()), " events left\n";
```

`OrderId::generate()` creates a new time-ordered UUID v7. `fromString()`
validates an existing one.

## Results and specifications

`Result` is a success with a value or a failure with a `DomainError`. Use
it for expected failures instead of exceptions. Specifications name
business rules and combine them:

<!-- example: examples/guides/ddd.php#result -->
```php
function parseCents(string $input): Result
{
    return ctype_digit($input) ? Result::ok((int) $input) : Result::fail(new DomainError('invalid_amount', "\"{$input}\" is not an amount."));
}

foreach (['1250', 'twelve'] as $input) {
    $result = parseCents($input)->map(static fn (int $cents): Money => new Money($cents, 'EUR'));
    echo $result->isOk() ? "ok: {$result->value()->cents}" : "failed: {$result->error()->code}: {$result->error()->message}", "\n";
}
```

<!-- example: examples/guides/ddd.php#specification -->
```php
$large = new CompositeSpecification(static fn (Order $o): bool => $o->total()->cents >= 1000);
$euro = new CompositeSpecification(static fn (Order $o): bool => $o->total()->currency === 'EUR');

var_export($large->and($euro)->isSatisfiedBy($order));
echo "\n";
var_export($large->not()->isSatisfiedBy($order));
echo "\n";
```

The whole example prints:

<!-- example: examples/guides/ddd.out -->
```text
true
true
OrderPlaced for 0190a8f0-1234-7000-8000-000000000001: 2000
0 events left
ok: 1250
failed: invalid_amount: "twelve" is not an amount.
true
false
```

## Rules worth knowing

- **Entities are equal by class and id.** Two loaded copies of the same
  order are the same order, whatever their state.
- **Release events after saving.** `releaseEvents()` returns the recorded
  events and clears them. Dispatch them once the unit of work has
  committed (see [events](events.md)).
- **Repositories and units of work are interfaces.**
  - Declare `RepositoryInterface` (generic over your aggregate) in the
    domain, and implement it in infrastructure.
  - `UnitOfWorkInterface::transactional()` commits, or rolls back on any
    exception. [zana/database](database.md) provides an implementation.
- **Optimistic concurrency:** `AggregateRoot` keeps a version, and
  persistence calls `incrementVersion()` when saving.
- **Commands and queries:** `Application\Command` and `Application\Query`
  are base classes for [mediator](mediator.md) messages, each with an id
  and a creation time.

Reference: the [ddd README](https://github.com/mjnable/zana-ddd).

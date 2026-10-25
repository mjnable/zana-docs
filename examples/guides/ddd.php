<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Ddd\Domain\AggregateRoot;
use Zana\Ddd\Domain\CompositeSpecification;
use Zana\Ddd\Domain\DomainError;
use Zana\Ddd\Domain\DomainEvent;
use Zana\Ddd\Domain\Identity;
use Zana\Ddd\Domain\Result;
use Zana\Ddd\Domain\ValueObject;

// region: value-object
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
// endregion: value-object

// region: aggregate
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
// endregion: aggregate

// region: usage
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
// endregion: usage

// region: result
function parseCents(string $input): Result
{
    return ctype_digit($input) ? Result::ok((int) $input) : Result::fail(new DomainError('invalid_amount', "\"{$input}\" is not an amount."));
}

foreach (['1250', 'twelve'] as $input) {
    $result = parseCents($input)->map(static fn (int $cents): Money => new Money($cents, 'EUR'));
    echo $result->isOk() ? "ok: {$result->value()->cents}" : "failed: {$result->error()->code}: {$result->error()->message}", "\n";
}
// endregion: result

// region: specification
$large = new CompositeSpecification(static fn (Order $o): bool => $o->total()->cents >= 1000);
$euro = new CompositeSpecification(static fn (Order $o): bool => $o->total()->currency === 'EUR');

var_export($large->and($euro)->isSatisfiedBy($order));
echo "\n";
var_export($large->not()->isSatisfiedBy($order));
echo "\n";
// endregion: specification

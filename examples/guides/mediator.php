<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Container\ServiceCollection;
use Zana\Mediator\Attribute\Handles;
use Zana\Mediator\HandlerMap;
use Zana\Mediator\Mediator;
use Zana\Mediator\PipelineBehavior;

// region: messages
final readonly class RegisterPatient
{
    public function __construct(public string $name)
    {
    }
}

#[Handles(RegisterPatient::class)]
final class RegisterPatientHandler
{
    public function __invoke(RegisterPatient $command): string
    {
        return "registered {$command->name}";
    }
}
// endregion: messages

// region: behavior
final readonly class Timing implements PipelineBehavior
{
    public function handle(object $message, \Closure $next): mixed
    {
        echo 'before ', $message::class, "\n";
        $result = $next($message);                 // or return early to short-circuit
        echo 'after ', $message::class, "\n";

        return $result;
    }
}
// endregion: behavior

// region: send
// In an application, MediatorServiceProvider wires this up; modules call
// HandlerMap::register() in boot() and list behaviours in mediator.behaviors.
$container = (new ServiceCollection())
    ->scoped(RegisterPatientHandler::class)        // handlers are resolved from the current scope
    ->singleton(Timing::class)
    ->build();
$handlers = (new HandlerMap())->register(RegisterPatientHandler::class);

$mediator = new Mediator($container->createScope(), $handlers, [Timing::class]);
echo $mediator->send(new RegisterPatient('Ada Lovelace')), "\n";
// endregion: send

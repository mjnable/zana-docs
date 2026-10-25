<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Container\Contracts\Disposable;
use Zana\Container\Exception\ContainerBuildException;
use Zana\Container\ScopeMetadata;
use Zana\Container\ServiceCollection;

interface Clock
{
    public function now(): string;
}

final class FixedClock implements Clock
{
    public function now(): string
    {
        return '2026-10-25 09:00';
    }
}

final class RequestLog implements Disposable
{
    /** @var list<string> */
    public array $lines = [];

    public function __construct(public readonly ScopeMetadata $scope)
    {
    }

    public function dispose(): void
    {
        echo "RequestLog of the {$this->scope->type} scope disposed\n";
    }
}

final readonly class Greeter
{
    public function __construct(private Clock $clock, private RequestLog $log)
    {
    }

    public function greet(string $name): string
    {
        $this->log->lines[] = $name;

        return "[{$this->clock->now()}] Hello, {$name}!";
    }
}

// region: register
$services = new ServiceCollection();
$services
    ->singleton(Clock::class, FixedClock::class)   // one instance for the whole application
    ->scoped(RequestLog::class)                     // one per scope (request, command, job)
    ->scoped(Greeter::class);                       // constructor parameters are injected by type

$container = $services->build();                    // validates the whole graph first
// endregion: register

// region: scopes
$first = $container->createScope(ScopeMetadata::http('req-1'));
$second = $container->createScope(ScopeMetadata::cli('report'));

echo $first->get(Greeter::class)->greet('Ada'), "\n";
echo $second->get(Greeter::class)->greet('Grace'), "\n";

echo 'same clock: ', var_export($first->get(Clock::class) === $second->get(Clock::class), true), "\n";      // singleton
echo 'same log: ', var_export($first->get(RequestLog::class) === $second->get(RequestLog::class), true), "\n";  // scoped

$first->dispose();
$second->dispose();
// endregion: scopes

// region: validation
$broken = new ServiceCollection();
$broken
    ->singleton(Greeter::class)          // a singleton...
    ->scoped(RequestLog::class)          // ...that depends on a scoped service
    ->singleton(Clock::class, FixedClock::class);

try {
    $broken->build();
} catch (ContainerBuildException $e) {
    foreach ($e->errors() as $error) {
        echo $error->code, ': ', $error->message, "\n";
    }
}
// endregion: validation

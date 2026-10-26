<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Container\Exception\ContainerBuildException;
use Zana\Container\Exception\ScopeException;
use Zana\Container\ServiceCollection;

final class EditorRepository
{
}

final class ResetTokens
{
    public function __construct(public readonly EditorRepository $editors)
    {
    }
}

// region: uses
$services = (new ServiceCollection())
    ->scoped(EditorRepository::class)
    ->singleton(ResetTokens::class, static fn ($r): ResetTokens => new ResetTokens($r->get(EditorRepository::class)))
    ->uses(EditorRepository::class);   // what the factory resolves

try {
    $services->build();
} catch (ContainerBuildException $e) {
    echo $e->errors()[0]->code, ': ', $e->errors()[0]->message, "\n";
}
// endregion: uses

// region: undeclared
// Without uses(), the build cannot see into the factory; the first resolution names both services.
$container = (new ServiceCollection())
    ->scoped(EditorRepository::class)
    ->singleton(ResetTokens::class, static fn ($r): ResetTokens => new ResetTokens($r->get(EditorRepository::class)))
    ->build();

try {
    $container->createScope()->get(ResetTokens::class);
} catch (ScopeException $e) {
    echo wordwrap(strtok($e->getMessage(), "\n"), 100), "\n";
}
// endregion: undeclared

<?php

declare(strict_types=1);

namespace Modules\Greetings;

use Modules\Greetings\Application\Greeter;
use Modules\Greetings\Presentation\Api\GreetAction;
use Modules\Greetings\Presentation\Cli\GreetCommand;
use Zana\Configuration\ConfigurationInterface;
use Zana\Console\ConsoleKernel;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\Contracts\ServiceResolverInterface;
use Zana\Core\Module;
use Zana\Routing\Router;

final class GreetingsModule extends Module
{
    public function name(): string
    {
        return 'Greetings';
    }

    public function register(ServiceCollectionInterface $services): void
    {
        $services
            // The module reads configuration; the application code gets plain values.
            ->singleton(Greeter::class, static fn (ServiceResolverInterface $r): Greeter => new Greeter(
                $r->get(ConfigurationInterface::class)->string('greetings.default_name'),
            ))
            ->transient(GreetAction::class)
            ->singleton(GreetCommand::class)->tag(ConsoleKernel::TAG, GreetCommand::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $router = $container->get(Router::class);
        $router->get('/hello', GreetAction::class);
        $router->get('/hello/{name}', GreetAction::class);
    }
}

# Getting started

This guide builds a small but complete Zana application: one module that
greets people over HTTP and from the command line, with configuration and a
test. It takes about ten minutes. The finished application is in
[`examples/getting-started/hello`](examples/getting-started/hello), and the
documentation's test suite runs it.

You need PHP 8.5 or later and Composer.

> **Building a website?** Start from the skeleton instead (2.3): pages in two
> languages, templates, security headers and upload archives for shared
> hosting, ready to run:
>
> ```
> composer create-project mj-zana/skeleton-web my-site
> ```
>
> Then read [a company website](cookbook/website.md) and
> [deployment](deployment.md).

## 1. Create the project

```
mkdir hello && cd hello
composer init --name=acme/hello --type=project -n
composer require mj-zana/core mj-zana/http mj-zana/routing mj-zana/console
composer require --dev mj-zana/testing phpunit/phpunit
```

Zana applications keep their own code in `src/` and their modules in
`modules/`. Map both in `composer.json`, then run `composer dump-autoload`:

```json
"autoload": {
    "psr-4": {
        "Hello\\": "src/",
        "Modules\\": "modules/"
    }
}
```

## 2. The composition root

One class decides which framework packages the application uses. Each
package contributes a *service provider*:

<!-- example: examples/getting-started/hello/src/Bootstrap.php -->
```php
<?php

declare(strict_types=1);

namespace Hello;

use Zana\Console\ConsoleServiceProvider;
use Zana\Core\Application;
use Zana\Core\ApplicationBuilder;
use Zana\Http\HttpServiceProvider;
use Zana\Routing\RoutingServiceProvider;

final class Bootstrap
{
    public static function builder(string $basePath): ApplicationBuilder
    {
        return Application::configure($basePath)
            ->withProviders(
                HttpServiceProvider::class,
                RoutingServiceProvider::class,
                ConsoleServiceProvider::class,
            );
    }
}
```

`build()` turns the builder into a running application. It loads the
configuration from `config/`, registers every provider and module, validates
the whole container, and then boots them. A configuration mistake fails here,
at startup, not halfway through a request.

The web front controller, `public/index.php`:

<!-- example: examples/getting-started/hello/public/index.php -->
```php
<?php

declare(strict_types=1);

use Hello\Bootstrap;
use Zana\Http\HttpKernel;

require dirname(__DIR__, 4) . '/vendor/autoload.php'; // in your project: dirname(__DIR__) . '/vendor/autoload.php'

HttpKernel::serve(Bootstrap::builder(dirname(__DIR__))->build());
```

And the console entry point, saved as `bin/zana`:

<!-- example: examples/getting-started/hello/bin/zana.php -->
```php
<?php

declare(strict_types=1);

use Hello\Bootstrap;
use Zana\Console\ConsoleKernel;

require dirname(__DIR__, 4) . '/vendor/autoload.php'; // in your project: dirname(__DIR__) . '/vendor/autoload.php'

exit(ConsoleKernel::runApplication(Bootstrap::builder(dirname(__DIR__))->build()));
```

## 3. A module

Features live in *modules*: self-contained folders with a module class that
registers their services. Start with the logic, an application service. It
receives plain values and knows nothing about where they come from:

<!-- example: examples/getting-started/hello/modules/Greetings/Application/Greeter.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Greetings\Application;

final readonly class Greeter
{
    public function __construct(private string $defaultName)
    {
    }

    public function greet(?string $name = null): string
    {
        $name ??= $this->defaultName;

        return "Hello, {$name}!";
    }
}
```

The default name is a setting in `config/greetings.php`. Every file in
`config/` becomes a section named after the file:

<!-- example: examples/getting-started/hello/config/greetings.php -->
```php
<?php

declare(strict_types=1);

// Available as greetings.*, e.g. greetings.default_name.
// Override with an environment variable: APP_GREETINGS__DEFAULT_NAME=Ada
return [
    'default_name' => 'world',
];
```

An HTTP action. Its constructor receives the `Greeter`. Route parameters
are passed by name, and an array return value becomes JSON:

<!-- example: examples/getting-started/hello/modules/Greetings/Presentation/Api/GreetAction.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Greetings\Presentation\Api;

use Modules\Greetings\Application\Greeter;

final readonly class GreetAction
{
    public function __construct(private Greeter $greeter)
    {
    }

    /**
     * GET /hello and GET /hello/{name}. An array becomes a JSON response.
     *
     * @return array{message: string}
     */
    public function __invoke(?string $name = null): array
    {
        return ['message' => $this->greeter->greet($name)];
    }
}
```

A console command. Zana commands are Symfony Console commands with a
`handle()` method whose parameters are injected:

<!-- example: examples/getting-started/hello/modules/Greetings/Presentation/Cli/GreetCommand.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Greetings\Presentation\Cli;

use Modules\Greetings\Application\Greeter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zana\Console\Command;

#[AsCommand(name: 'greet', description: 'Print a greeting')]
final class GreetCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Who to greet');
    }

    public function handle(Greeter $greeter, InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $output->writeln($greeter->greet(is_string($name) ? $name : null));

        return self::SUCCESS;
    }
}
```

The module class ties them together:

- **`register()`** declares services. Zana's container only creates what is
  registered.
  - **Class names:** constructor parameters that are services are injected
    automatically.
  - **Factories:** a factory builds `Greeter` from the setting. The module is
    composition code, so it may read configuration; the application code
    stays independent of it.
- **`boot()`** runs once the container is built, and adds the routes.

<!-- example: examples/getting-started/hello/modules/Greetings/GreetingsModule.php -->
```php
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
```

Modules are enabled explicitly, in `config/modules.php`:

<!-- example: examples/getting-started/hello/config/modules.php -->
```php
<?php

declare(strict_types=1);

return [
    Modules\Greetings\GreetingsModule::class,
];
```

## 4. Run it

```
php -S 127.0.0.1:8080 -t public
curl http://127.0.0.1:8080/hello/Ada        # {"message":"Hello, Ada!"}

php bin/zana greet                           # Hello, world!
APP_GREETINGS__DEFAULT_NAME=Ada php bin/zana greet
```

Each request and each command runs in its own *scope*. Services registered
as `scoped` live for exactly one request or command. Errors become proper
responses: a JSON problem document for API clients, an HTML page for
browsers.

## 5. Test it

`ApplicationTestCase` from mj-zana/testing builds the application for each
test. It sends requests and runs commands in memory, so there is no server
and no output:

<!-- example: examples/getting-started/hello/tests/GreetingTest.php -->
```php
<?php

declare(strict_types=1);

namespace Hello\Tests;

use Hello\Bootstrap;
use Zana\Core\ApplicationInterface;
use Zana\Testing\ApplicationTestCase;

final class GreetingTest extends ApplicationTestCase
{
    protected function createApplication(): ApplicationInterface
    {
        return Bootstrap::builder(dirname(__DIR__))->withEnvironment('testing')->build();
    }

    public function testTheGreetingOverHttp(): void
    {
        $this->http()->get('/hello')->assertOk()->assertJson(['message' => 'Hello, world!']);
        $this->http()->get('/hello/Ada')->assertOk()->assertJsonPath('message', 'Hello, Ada!');
    }

    public function testTheGreetingFromTheCommandLine(): void
    {
        $this->console()->run('greet Grace')->assertSuccessful()->assertOutputContains('Hello, Grace!');
    }
}
```

```
vendor/bin/phpunit tests
```

## Where next

- **[The module cookbook](cookbook/modules.md):** how to grow this into a
  real application, with layers, persistence, events, module contracts and
  architecture tests.
- **[The guides](guides/README.md):** one per package, starting with the
  [container](guides/container.md) and the [application](guides/core.md).
- **The BMI reference application:** two modules, REST, web and CLI,
  SQLite and MySQL. The cookbook draws on it. It is not published yet.

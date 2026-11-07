# Console

`mj-zana/console` runs Symfony Console commands with two additions: each
command runs in its own container scope, and `handle()` receives its
dependencies. Plain Symfony commands work unchanged.

```
composer require mj-zana/console
```

## A command

<!-- example: examples/guides/console.php#command -->
```php
#[AsCommand(name: 'stock:check', description: 'Show the stock of a product')]
final class StockCheckCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('product', InputArgument::REQUIRED)
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Fail when out of stock');
    }

    // handle() receives services from the command's scope, plus the input, output or a SymfonyStyle.
    public function handle(Inventory $inventory, ScopeMetadata $scope, InputInterface $input, SymfonyStyle $io): int
    {
        $product = (string) $input->getArgument('product');
        $count = $inventory->stock[$product] ?? 0;
        $io->writeln("{$product}: {$count} in stock (scope: {$scope->type}:{$scope->name})");

        return $count === 0 && $input->getOption('strict') ? self::FAILURE : self::SUCCESS;
    }
}
```

Commands are services tagged `ConsoleKernel::TAG`:

<!-- example: examples/guides/console.php#register -->
```php
$app = Application::configure(sys_get_temp_dir() . '/zana-docs-console')
    ->withProviders(ConsoleServiceProvider::class)
    ->withServices(static fn (ServiceCollectionInterface $s) => $s
        ->singleton(Inventory::class)
        ->singleton(StockCheckCommand::class)->tag(ConsoleKernel::TAG, StockCheckCommand::class))
    ->build();
```

## Running

<!-- example: examples/guides/console.php#run -->
```php
// In bin/zana: exit(ConsoleKernel::runApplication($app));  Here, in memory:
$console = new ConsoleTester($app->container()->get(ConsoleKernel::class));

$ok = $console->run('stock:check scale');
echo trim($ok->output()), " [exit {$ok->exitCode()}]\n";

$out = $console->run('stock:check tape --strict');
echo trim($out->output()), " [exit {$out->exitCode()}]\n";
```

<!-- example: examples/guides/console.out -->
```text
scale: 4 in stock (scope: cli:stock:check) [exit 0]
tape: 0 in stock (scope: cli:stock:check) [exit 1]
```

## Rules worth knowing

- **Return values:** `handle()` returns an exit code, or nothing for
  success.
- **Failures:** exceptions are rendered as an error and exit with code 1.
  Symfony's automatic `exit()` is turned off, so the code is returned.
- **One scope per run.** Scoped services are fresh for each command and
  disposed afterwards. The scope carries the command name and a
  correlation id, which also goes into log lines. A valid
  `ZANA_CORRELATION_ID` environment variable is reused, so a scheduler can
  pass its own.
- **Values starting with `-` are options to Symfony.** Pass negative
  numbers after `--`, e.g. `bmi:calculate -- 170 -5`.
- **Why Symfony Console?** Argument parsing, help, styling and completion
  are solved problems. Zana adds the scope and the injection. Symfony's
  console documentation applies to everything else.

## Inspect and check (2.7)

Two commands show the application and verify it, for developers and for
[AI coding agents](../ai-agents.md). Both take `--format=json`.

- **`inspect [section]`** shows the environment, the modules, the services
  with their lifetimes and origins, the console commands, the
  configuration keys with their types (never their values) and the
  installed packages. With `mj-zana/routing`, the routes; with
  `mj-zana/database`, the migrations. `inspect routes` shows one section.
- **`check`** verifies that the container builds, the startup deployment
  checks, that every singleton can be created, the architecture rules of
  `mj-zana/testing` on `modules/` (when it is installed), and every check
  the packages and the application add: route handlers and middleware are
  registered and route middleware can be created (`mj-zana/routing`), the
  global middleware of `http.middleware` can be created (`mj-zana/http`,
  2.10), migrations are applied and their files exist
  (`mj-zana/database`). When the container does not build, `check` still
  runs and reports why.

Each problem is a finding with an id (`routing.handler`,
`container.singleton`, `architecture.dependency`, …), a severity (`error`,
`warning`, `info`), a message, the file when there is one, and a hint.
`check` exits with 1 when there is an error. The JSON is
`{"schema": "zana.check/1", "status", "passed": [...], "findings": [...]}`;
`inspect` uses `zana.inspect/1`. Fields are only added within a version.

Add a check of your own by tagging a service that implements
`ApplicationCheck` (from `mj-zana/core`):

<!-- example: examples/guides/check.php#contribute -->
```php
// An application or package adds its own checks: a service tagged ApplicationCheck::TAG.
final class OpeningHoursCheck implements ApplicationCheck
{
    public function findings(ApplicationInterface $application): array
    {
        $hours = $application->config()->get('shop.opening_hours', '');

        return $hours === ''
            ? [new Finding('shop.opening_hours', Severity::Warning, 'The opening hours are not set.', hint: "Set shop.opening_hours in config/shop.php.")]
            : [];
    }
}
```

<!-- example: examples/guides/check.php#check -->
```php
$console = new ConsoleTester($app->container()->get(ConsoleKernel::class));
$result = $console->run('check --format=json');

$report = json_decode($result->output(), true);
echo "{$report['schema']}: {$report['status']} [exit {$result->exitCode()}]\n";
foreach ($report['findings'] as $finding) {
    echo "{$finding['severity']} {$finding['id']}: {$finding['message']}\n  hint: {$finding['hint']}\n";
}
```

<!-- example: examples/guides/check.out -->
```text
zana.check/1: error [exit 1]
error routing.handler: GET /shelves: its handler ShelfController is not registered in the container.
  hint: Register it in its module's register(): $services->transient(\ShelfController::class);
warning shop.opening_hours: The opening hours are not set.
  hint: Set shop.opening_hours in config/shop.php.
```

A section for `inspect` is a service tagged `InspectionSection::TAG`, with
a `name()` and an `inspect()` that returns an array.

In `bin/console`, `ConsoleKernel::runBuilder(Bootstrap::builder(...))`
builds the application itself, so `check` can report a container that does
not build (the 2.7 skeleton does this). `doctor` also takes `--format=json`
since 2.7, with the same report format.

## New in 2.2

| Command | |
|---|---|
| `cache:clear` | Deletes the compiled container and runs every cache clearer (compiled Twig templates, …). Run it after each deployment. |
| `cache:warm` | Compiles the container and runs every cache warmer. |
| `about` | The application, its environment, PHP, and the installed Zana packages. |
| `doctor` | Checks the server before going live: the PHP version, the extensions in `app.extensions`, a writable `var/`, the length of `security.secret`, the startup deployment checks, and (2.4) that every singleton can be created. Exits with 1 when something is fatal. |
| `mail:test` | (2.6, with `mj-zana/communication`) Sends a test email and prints the mail server's answer, passwords hidden. |
| `release:build` | (2.5) Builds the upload archives for shared hosting from a commit, with the production dependencies installed. Options: `--folder`, `--ref`, `--output`, `--composer`, `--timeout`, `--no-public` (2.18), `-v`. See [deployment](../deployment.md#without-ssh) and [what the archives hold](../deployment.md#what-the-archives-hold). Needs `ext-zip`. |

Packages and applications add their caches by tagging a service with
`CacheClearerInterface::CLEARER_TAG` or `CacheWarmerInterface::WARMER_TAG`
(from `mj-zana/core`).

With a compiled container, commands that change the code (`make:*`) leave
it stale; `ApplicationBuilder::recompileStaleContainerInConsole()` rebuilds
it when the console starts.

Reference: the [console README](https://github.com/mjnable/zana-console).

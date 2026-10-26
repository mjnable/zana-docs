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

## New in 2.2

| Command | |
|---|---|
| `cache:clear` | Deletes the compiled container and runs every cache clearer (compiled Twig templates, …). Run it after each deployment. |
| `cache:warm` | Compiles the container and runs every cache warmer. |
| `about` | The application, its environment, PHP, and the installed Zana packages. |
| `doctor` | Checks the server before going live: the PHP version, the extensions in `app.extensions`, a writable `var/`, the length of `security.secret`, and the startup deployment checks. Exits with 1 when something is fatal. |

Packages and applications add their caches by tagging a service with
`CacheClearerInterface::CLEARER_TAG` or `CacheWarmerInterface::WARMER_TAG`
(from `mj-zana/core`).

With a compiled container, commands that change the code (`make:*`) leave
it stale; `ApplicationBuilder::recompileStaleContainerInConsole()` rebuilds
it when the console starts.

Reference: the [console README](https://github.com/mjnable/zana-console).

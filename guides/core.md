# Application and modules

`mj-zana/core` turns packages and modules into a running application. It
loads the configuration, registers every provider and module, validates and
builds the container, then boots everything in order.

```
composer require mj-zana/core
```

## Providers and modules

- **A service provider** registers a package's services in `register()`,
  and finishes setting up in `boot()`, once the container is built.
- **A module** is a provider with a name, dependencies on other modules,
  and a folder of its own. Your features live in modules.

<!-- example: examples/guides/core.php#modules -->
```php
final class CatalogModule extends Module
{
    public function name(): string
    {
        return 'Catalog';
    }

    public function register(ServiceCollectionInterface $services): void
    {
        $services->singleton(Catalog::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $container->get(Catalog::class)->products[] = 'Scale';
        echo "booted Catalog\n";
    }
}

final class OrdersModule extends Module
{
    public function name(): string
    {
        return 'Orders';
    }

    public function dependsOn(): array
    {
        return [CatalogModule::class];   // booted after Catalog, and Catalog must be enabled
    }

    public function register(ServiceCollectionInterface $services): void
    {
    }

    public function boot(ContainerInterface $container): void
    {
        echo 'booted Orders, catalog has: ', implode(', ', $container->get(Catalog::class)->products), "\n";
    }
}
```

## Building the application

<!-- example: examples/guides/core.php#build -->
```php
$app = Application::configure(sys_get_temp_dir() . '/zana-docs-core')
    ->withEnvironment('development')
    ->withProviders(AuditProvider::class)
    ->withModules(OrdersModule::class, CatalogModule::class)   // usually listed in config/modules.php
    ->build();

echo 'environment: ', $app->environment()->name, ', debug: ', var_export($app->environment()->debug, true), "\n";
echo 'modules: ', implode(', ', array_map(static fn (Module $m): string => $m->name(), $app->modules())), "\n";

$app->terminate();   // disposes the container
```

<!-- example: examples/guides/core.out -->
```text
booted AuditProvider
booted Catalog
booted Orders, catalog has: Scale
environment: development, debug: true
modules: Catalog, Orders
```

Providers boot first, then modules in dependency order, whatever order
they were listed in.

## The lifecycle

1. Load `.env`, then pick the environment: `withEnvironment()`, else
   `APP_ENV`, else `production`. Debug is on outside production unless
   `APP_DEBUG` says otherwise.
2. Read the module list: `config/modules.php` plus `withModules()`.
   Modules are never discovered, only listed.
3. Load the configuration:
   - each module's `Configuration/*.php`, under the module's name, e.g.
     `bmi.database`;
   - then `config/*.php` and `config/<environment>/*.php`;
   - then `APP_*` environment variables, and `withConfiguration()`.

   In production with `withConfigurationCache()` (2.4), the module list and
   the files' values come from one cache file instead; the environment
   variables and overrides still apply on top.
4. Register the providers, then the modules, then `withServices()`
   callbacks. Each registration records its origin, e.g. `module:Orders`,
   for error messages.
5. Build and validate the container, or load the compiled one
   (`withContainerCache()`).
6. Boot the providers, then the modules.

## Rules worth knowing

- **`register()` only registers.** Resolving services there fails, because
  the container does not exist yet. Do that in `boot()`.
- **Module paths.** `$module->path('Infrastructure', 'Persistence')` points
  into the module's own folder. Migrations are found that way.
- **Entry points:**
  - `HttpKernel::serve($app)` runs a web request;
  - `ConsoleKernel::runApplication($app)` runs the console.
  - Both terminate the application afterwards.
- **Correlation ids.** `Zana\Core\Correlation` gives every request and
  command an id that every log line carries (see [logging](logging.md)).

## New in 2.1

- **Deployment checks.** After boot, the application refuses to start
  (`UnsafeDeploymentException`) when debug is on for a public `app.url`,
  when debug is on in production, or when a secret is still a
  placeholder in production. The secrets checked are listed in
  `app.secret_keys`. Add your own checks as services tagged
  `core.deployment_checks`.
- **Configuration overrides.** `withConfiguration(['site.url' => '...'])`
  adds a last layer, above environment variables.
- **`.env.testing`.** In the testing environment it replaces `.env` when it
  exists.

## New in 2.4: the configuration cache

Reading configuration is most of a boot: every request scans `config/` and
`config/<environment>/` and loads each file. In production, keep the result
in one file:

```php
HttpKernel::run(
    Bootstrap::builder($base)
        ->withContainerCache($base . '/var/cache/container.php')
        ->withConfigurationCache($base . '/var/cache/config.php'),
);
```

- **What is cached:** the values of the configuration files (modules'
  `Configuration/`, `config/`, `config/<environment>/`) and the module list
  of `config/modules.php`.
- **What stays live:** `.env`, `APP_*` environment variables and
  `withConfiguration()` overrides apply on top at every boot, so secrets and
  addresses never end up in the cache.
- **Production only.** Elsewhere the files are always read, so edits take
  effect at once. In production the cache is trusted without looking at the
  files: it is written on the first boot without it, `cache:clear` deletes
  it and `cache:warm` writes it. Clear it on every deployment.
- **Plain values only.** A configuration file that returns an object or a
  closure cannot be cached: the application then boots without the cache,
  and `cache:warm` fails, naming the key.
- **Do not read the environment in configuration files** (`$_ENV`,
  `getenv()`): the cache would freeze the value. Leave the key out and set
  `APP_A__B` on the server instead.

On the benchmark application, a cached boot plus one request went from
about 900 µs to 510 µs with one configuration file, and from 1,530 µs to
516 µs with five: boot no longer grows with the number of files.

Reference: the [core README](https://github.com/mjnable/zana-core).

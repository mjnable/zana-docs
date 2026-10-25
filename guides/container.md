# Container

`mj-zana/container` creates your services and wires their dependencies. You
rarely use it directly: providers and modules register services, and
`zana/core` builds the container. Knowing its rules explains most of what the
rest of Zana does.

```
composer require mj-zana/container
```

## Register, build, resolve

Services are registered on a `ServiceCollection`, then `build()` turns the
collection into a container. Each service has a **lifetime**:

- **singleton:** one instance for the whole application;
- **scoped:** one instance per scope (an HTTP request, a console command, a
  queue job);
- **transient:** a new instance each time.

<!-- example: examples/guides/container.php#register -->
```php
$services = new ServiceCollection();
$services
    ->singleton(Clock::class, FixedClock::class)   // one instance for the whole application
    ->scoped(RequestLog::class)                     // one per scope (request, command, job)
    ->scoped(Greeter::class);                       // constructor parameters are injected by type

$container = $services->build();                    // validates the whole graph first
```

Scoped services are resolved from a scope. A scope's services that
implement `Disposable` are disposed with it:

<!-- example: examples/guides/container.php#scopes -->
```php
$first = $container->createScope(ScopeMetadata::http('req-1'));
$second = $container->createScope(ScopeMetadata::cli('report'));

echo $first->get(Greeter::class)->greet('Ada'), "\n";
echo $second->get(Greeter::class)->greet('Grace'), "\n";

echo 'same clock: ', var_export($first->get(Clock::class) === $second->get(Clock::class), true), "\n";      // singleton
echo 'same log: ', var_export($first->get(RequestLog::class) === $second->get(RequestLog::class), true), "\n";  // scoped

$first->dispose();
$second->dispose();
```

## Mistakes fail at startup

`build()` checks the whole graph before creating anything. For example, a
singleton must never hold a scoped service, because it would keep the first
request's instance forever:

<!-- example: examples/guides/container.php#validation -->
```php
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
```

The whole example prints:

<!-- example: examples/guides/container.out -->
```text
[2026-10-25 09:00] Hello, Ada!
[2026-10-25 09:00] Hello, Grace!
same clock: true
same log: false
RequestLog of the http scope disposed
RequestLog of the cli scope disposed
B5: Greeter: Greeter (singleton) → RequestLog (scoped): a singleton must not depend on a scoped service
```

Other checks catch missing dependencies, cycles, ambiguous union types, and
misused attributes. Each error has a code (B1 to B14) and names the
registration and the module it came from.

## Rules worth knowing

- **Only registered services are created.** `get(SomeClass::class)` fails
  unless `SomeClass` is registered, even if the class exists. Explicit
  registration is what makes startup validation possible.
- **The owning resolver.** A singleton's dependencies always come from the
  root, a scoped service's from its scope. A singleton can never capture a
  request's data by accident.
- **No service locator.** Only composition code (providers, modules,
  factories) touches the container. Your classes declare what they need in
  their constructors.
- **Registration options:**
  - **Keyed services:** `->singleton(Cache::class, RedisCache::class, key: 'sessions')`.
  - **Tags:** `->tag('reports', ...)`.
  - **Decorators:** `->decorate()`.
  - **Conditional registration:** `when()` and `profile()`.
  - **Defaults and overrides:** `try*` registers only when nothing is
    registered yet, `replace()` changes an existing registration.
  - **Attributes:** `#[Singleton]`, `#[Inject]`, `#[Tagged]`, plus
    `scan()` to register attributed classes from a folder.
  - **Lazy services:** `lazy: true` gives a native PHP 8.4+ lazy object.
- **Production.** `ApplicationBuilder::withContainerCache()` compiles the
  container to a PHP file. It is reused while the registrations stay the
  same; in production a stale file is an error.

Reference: the [container README](https://github.com/mjnable/zana-container).

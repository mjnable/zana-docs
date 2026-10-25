# Upgrading from 1.x

Zana 1.x shipped three packages: `zana/container`, `mj-zana/ddd` and
`mj-zana/contracts`. Zana 2.0 is a full framework of 19 packages. This page
covers what changes for code written against 1.x; everything else in 2.0 is
new.

## Requirements

- **PHP 8.5 or later.** 1.x needed PHP 8.1.
- **Composer vendor `mj-zana`.** The container moves from `zana/container`
  to `mj-zana/container`. PHP namespaces stay `Zana\…`.
- **One version for every package.** All 2.x packages share the same
  version number, so require them with the same constraint, e.g. `^2.0`.

```
composer remove zana/container mj-zana/contracts
composer require mj-zana/container:^2.0 mj-zana/ddd:^2.0
```

## `mj-zana/contracts` is abandoned

There is no 2.x release. Each 2.0 package owns its own contracts:

| 1.x (`Zana\Contracts\…`) | 2.0 |
|---|---|
| `Container\*` | `Zana\Container\Contracts\*` in `mj-zana/container` |
| `Configuration\ConfigurationInterface` | `Zana\Configuration\ConfigurationInterface` in `mj-zana/configuration` |
| `Application\ApplicationInterface` | `Zana\Core\ApplicationInterface` in `mj-zana/core` |
| `Application\ConsoleKernelInterface` | `Zana\Console\ConsoleKernel` in `mj-zana/console` |
| `Application\CommandInterface`, handlers | `Zana\Ddd\Application\Command` and `Query`, with `#[Zana\Mediator\Attribute\Handles]` handlers |
| `Mediator\MediatorInterface` | `Zana\Mediator\MediatorInterface` in `mj-zana/mediator` |
| `Pipeline\*` | `Zana\Mediator\PipelineBehavior`, or PSR-15 middleware for HTTP |
| `Http\RequestInterface`, `ResponseInterface` | PSR-7 `ServerRequestInterface` and `ResponseInterface` |
| `Http\MiddlewareInterface` | PSR-15 `MiddlewareInterface` |
| `Http\KernelInterface` | `Zana\Http\HttpKernel` in `mj-zana/http` |
| `Http\Exceptions\*` | `Zana\Http\Exception\*` in `mj-zana/http` |
| `Routing\*` | `Zana\Routing\Router` and `Route` in `mj-zana/routing` |
| `Database\ConnectionInterface` | `Zana\Database\ConnectionInterface` in `mj-zana/database` |
| `Domain\RepositoryInterface` | `Zana\Ddd\Domain\RepositoryInterface` in `mj-zana/ddd` |

## The container

Registration looks the same: `ServiceCollection` with `singleton()`,
`scoped()`, `transient()`, `instance()` and `alias()`, then `build()`. What
changed is how strict the container is.

| 1.x | 2.0 | What to do |
|---|---|---|
| Any existing class could be resolved, registered or not | Only registered services are resolved | Register the classes you resolve. `build()` lists what is missing |
| `build()` checked nothing | `build()` validates the whole graph: missing dependencies, cycles, lifetimes, attributes (errors B1–B14) | Fix what it reports, at startup instead of in production |
| A scoped service resolved from the root silently became a singleton | Resolving a scoped service from the root throws | Resolve it from a scope (`createScope()`), or make it a singleton |
| A singleton could hold a scoped service | Build error B5 | Make the dependent service scoped, or the dependency a singleton |
| The resolver was named `ServiceProvider` and had `register()` and `boot()` | The resolver is `Container` (`ContainerInterface`); providers are `Zana\Core\ServiceProvider` | Move registration code into a provider or a module |
| `tryGet()` | Removed | Use `has()`, a nullable constructor parameter, or `try*` registration methods |
| `instance()` replaced earlier registrations | It adds one; `get()` returns the latest, `getAll()` returns every one | Usually nothing |
| `ScopeMetadata` had typed `correlationId`, `tenantId`, `userId` and a `ScopeType` enum | A `type` string, a `name`, and generic attributes; `ScopeKeys` names the common ones | `ScopeMetadata::http($id)->withAttribute(ScopeKeys::TENANT_ID, $tenant)` |
| Disposal relied on `__destruct` | Explicit: `dispose()` on scopes and the container, `Disposable` on services | Implement `Disposable` where you release resources |

New in 2.0 are keyed services, tags, decorators, profiles, attributes and
scanning, parameter providers, union types, property and method injection,
`call()`, interceptors, lazy services, scope observers, the service graph,
and a compiled container. See the [container guide](guides/container.md).

## `mj-zana/ddd`

| 1.x | 2.0 | What to do |
|---|---|---|
| `ValueObject` was a normal abstract class with `with(array)` | `ValueObject` is `abstract readonly`: subclasses must be `readonly`, and `with()` is gone | Declare value objects `final readonly class`, and write named methods that return a new instance |
| `DomainEvent` ids came from `uniqid('evt-')` | Ids are UUID v7, and the constructor also takes `occurredAt` | Nothing, unless you parsed event ids |
| `Entity`, `AggregateRoot`, `Command`, `Query` | Unchanged in use | Nothing |

New in 2.0 are `Identity` and `Uuid` (typed ids, UUID v7), `Result` with
`Success` and `Failure`, `DomainError`, `Specification`,
`RepositoryInterface`, `UnitOfWorkInterface`, `DomainException` and
`EntityNotFoundException`. See the [DDD guide](guides/ddd.md).

## A checklist

1. Move to PHP 8.5, and rename `zana/container` to `mj-zana/container` in
   `composer.json`.
2. Replace every `Zana\Contracts\…` import using the table above, and
   remove `mj-zana/contracts`.
3. Make value objects `readonly`, and replace `with()` calls.
4. Move registration into providers or modules, and build the application
   with `Zana\Core\Application::configure()` ([getting started](getting-started.md)).
5. Run the application. The container reports every unregistered
   dependency and lifetime mistake at startup, with the module each
   registration came from.
6. Add the architecture test ([testing](guides/testing.md)) to keep the
   new module boundaries.

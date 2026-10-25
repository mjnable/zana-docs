# Zana documentation

Zana is a modular PHP framework for business applications. You build an
application from **modules**: self-contained features with their own
domain, use cases, persistence and presentations. Zana gives them:

- **A strict container:** it validates the whole application at startup.
- **One scope per unit of work:** a container scope for every HTTP request,
  console command and queue job.
- **Standard interfaces:** PSR-3, 7, 11, 14, 15, 16 and 17.
- **Tools that keep modules apart:** contracts between modules, and
  architecture tests that enforce the boundaries.

Zana 2.0 requires PHP 8.5 or later. **Status:** `2.0.0`, released on
2026-10-26. See the [release notes](release-notes.md).

## Start here

- **[Getting started](getting-started.md):** build a small application with
  one module, HTTP, a console command and a test, in about ten minutes.
- **[The module cookbook](cookbook/modules.md):** grow it into a real
  application, with layers, persistence, events, contracts between modules
  and testing by layer.
- **[The guides](guides/README.md):** one per package, each with a tested
  example.
- **[Upgrading from 1.x](upgrading.md)** and the
  **[release notes](release-notes.md).**
- **[Contributing](contributing.md).**

## The packages

| Area | Packages |
|---|---|
| Foundation | [container](guides/container.md), [core](guides/core.md), [configuration](guides/configuration.md), [logging](guides/logging.md), [events](guides/events.md) |
| Delivery | [http](guides/http.md), [routing](guides/routing.md), [console](guides/console.md), [security](guides/security.md) |
| Business logic | [ddd](guides/ddd.md), [mediator](guides/mediator.md), [validation](guides/validation.md), [serialization](guides/serialization.md) |
| Infrastructure | [database](guides/database.md), [cache](guides/cache.md), [filesystem](guides/filesystem.md), [queue](guides/queue.md) |
| Tooling | [testing](guides/testing.md), [maker](guides/maker.md) |

Each is installed as `mj-zana/<name>`; the source is on GitHub as
`mjnable/zana-<name>`.

## Tested documentation

Every code sample on these pages comes from a runnable file in
[`examples/`](examples). The test suite installs the released packages from
Packagist and checks that:

- every embedded block matches its file;
- every example prints the output the page shows;
- the example applications pass their own tests.

```
composer install
composer test
```

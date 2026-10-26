# Guides

One guide per package. Each shows the package in a runnable example, checked
by this repository's tests against the released packages, and the rules
worth knowing before you use it. The package READMEs on GitHub have the full
reference.

Every package is installed as `mj-zana/<name>` and requires PHP 8.5 or later.

## The foundation

| Guide | Package | What it gives you |
|---|---|---|
| [Container](container.md) | `mj-zana/container` | Services, lifetimes, scopes, startup validation |
| [Application and modules](core.md) | `mj-zana/core` | The boot lifecycle, providers, modules, environments |
| [Configuration](configuration.md) | `mj-zana/configuration` | Layered settings: files, environment, `.env` |
| [Logging](logging.md) | `mj-zana/logging` | PSR-3 logging with channels and correlation ids |
| [Events](events.md) | `mj-zana/events` | PSR-14 events with lazy listeners |

## Delivering the application

| Guide | Package | What it gives you |
|---|---|---|
| [HTTP](http.md) | `mj-zana/http` | The PSR-15 kernel, middleware, error responses |
| [Routing](routing.md) | `mj-zana/routing` | Routes, groups, attributes, URL generation |
| [Console](console.md) | `mj-zana/console` | Commands with injection, a scope per run |
| [Security](security.md) | `mj-zana/security` | Passwords, bearer tokens, roles and policies, CSRF |
| [Views](view.md) | `mj-zana/view`, `mj-zana/view-twig`, `mj-zana/view-markdown` | HTML templates, layouts, error pages (2.1), assets, page metadata, Markdown (2.2) |
| [Translation](translation.md) | `mj-zana/translation` | Catalogs, the request locale, negotiation (2.1) |
| [Sessions](session.md) | `mj-zana/session` | Server-side sessions and flash messages (2.2) |

## Business logic

| Guide | Package | What it gives you |
|---|---|---|
| [Domain-driven design](ddd.md) | `mj-zana/ddd` | Entities, aggregates, value objects, events, results |
| [Mediator](mediator.md) | `mj-zana/mediator` | Commands and queries with pipeline behaviours |
| [Validation](validation.md) | `mj-zana/validation` | Attribute constraints with field paths |
| [Serialization](serialization.md) | `mj-zana/serialization` | Objects to and from arrays and JSON |

## Infrastructure

| Guide | Package | What it gives you |
|---|---|---|
| [Database](database.md) | `mj-zana/database` | PDO, a query builder, schema, migrations, unit of work |
| [Cache](cache.md) | `mj-zana/cache` | PSR-16 caches: memory, files, null |
| [Filesystem](filesystem.md) | `mj-zana/filesystem` | Local and in-memory storage behind one interface |
| [Queue](queue.md) | `mj-zana/queue` | Background jobs, retries, a scope per job |
| [Communication](communication.md) | `mj-zana/communication` | Email with safe sender rules (2.1), newsletters (2.2) |
| [Media](media.md) | `mj-zana/media` | Uploaded images: resized variants, `<picture>`, alt texts (2.2) |
| [Scheduler](scheduler.md) | `mj-zana/scheduler` | Recurring tasks from one cron entry (2.2) |
| [Audit](audit.md) | `mj-zana/audit` | Revisions of edited content, per locale (2.2) |

## Tooling

| Guide | Package | What it gives you |
|---|---|---|
| [Testing](testing.md) | `mj-zana/testing` | Application tests, HTTP and console in memory, architecture rules |
| [Maker](maker.md) | `mj-zana/maker` | Code generators for modules, features, entities, controllers, pages and back offices |

To see how the packages fit together in one application, read the
[module cookbook](../cookbook/modules.md). For a server-rendered,
multilingual site, read [a company website](../cookbook/website.md), then
[a back office](../cookbook/back-office.md) and the
[recipes for content sites](../cookbook/recipes.md).

# Release notes — Zana 2

Zana 2.0 turns the three 1.x packages into a framework of 19 packages for
modular business applications, on PHP 8.5. Zana 2.1 adds what server-rendered
websites need, 2.2 what content sites need, and 2.3 the way to put them online. Every package carries the
same version number. Each repository has its own `CHANGELOG.md`.

## 2.3.0 — 2026-10-26

2.3 finishes the website work. It is backward compatible with 2.2.

- **New project:** `mj-zana/skeleton-web` for `composer create-project`: a
  website in two languages with page metadata, versioned assets, a language
  switcher, security headers, translated error pages and tests, `.htaccess`
  files for a fixed document root, and `release:build` for shared hosting.
- **routing:** redirect routes (`Router::redirect()`), kept in the route
  cache.
- **Every package:** tests and tooling are no longer in Composer archives.
- **Docs:** [deployment](deployment.md).

## 2.2.0 — 2026-10-26

2.2 adds what a content site needs once its pages exist: a back office,
images, caching, SEO and the jobs that keep it running. It is backward
compatible with 2.1.

- **New packages:**
  - `mj-zana/session`: server-side sessions and flash messages;
  - `mj-zana/media`: uploaded images with AVIF, WebP and JPEG variants, a
    responsive `<picture>`, and alt texts per locale;
  - `mj-zana/view-markdown`: a safe `markdown` view helper;
  - `mj-zana/scheduler`: recurring tasks from one cron entry;
  - `mj-zana/audit`: revisions of edited content, per locale.
- **maker:** `make:crud` generates a complete back office; `make:page` and
  `--web` generate website pages and controllers.
- **security:**
  - session sign-in with attempt limits;
  - password reset tokens;
  - `#[RateLimit]`;
  - honeypot and minimum-age anti-spam for forms.
- **http:**
  - trusted proxies and the client address;
  - sub-folder deployments;
  - `FormInput`;
  - `#[Cache]` with ETags;
  - a page cache invalidated by tags.
- **routing:** route metadata.
- **translation:** a remembered language choice, `LocaleSwitchAction`,
  and `LocalizedValue` for content.
- **view:** versioned assets, `PageMeta` (titles, canonical, `hreflang`,
  Open Graph, JSON-LD) and the `picture` helper.
- **communication:** newsletters with double opt-in and one-click
  unsubscribe.
- **console:** `cache:clear`, `cache:warm`, `about` and `doctor`.
- **core:** cache clearers and warmers, and an optional recompile of a
  stale container in the console.

**Upgrading from 2.1:** delete the compiled container cache, as the
registrations change, or run `php bin/console cache:clear`.

Read [a back office](cookbook/back-office.md) and the
[recipes](cookbook/recipes.md) for it at work.

## 2.1.0 — 2026-10-26

2.1 adds what the first public websites built on Zana had to write
themselves. It is backward compatible with 2.0.

- **New packages:**
  - `mj-zana/view`: templates per module, layouts, escaping by default,
    `View` route results, and templated error pages;
  - `mj-zana/view-twig`: Twig as the view engine;
  - `mj-zana/translation`: catalogs, locale negotiation and the request
    locale;
  - `mj-zana/communication`: email with safe sender rules and a recorder
    for tests.
- **routing:** localised routes (`Router::localized()`), `alternates()`, URL
  defaults, and result converters.
- **http:**
  - `SecurityHeadersMiddleware` (CSP with nonces, HSTS);
  - pluggable HTML error pages;
  - `HttpKernel::run()` for a safe 503 when the application cannot start;
  - no `X-Powered-By` banner.
- **security:** stateless CSRF protection.
- **core:**
  - deployment checks at startup;
  - `withConfiguration()` overrides;
  - `.env.testing`.
- **validation:** translatable violation messages.
- **testing:**
  - `browser()` and HTML assertions;
  - form submission with hidden fields;
  - `withConfig()` per test;
  - mail assertions.

**Upgrading from 2.0:**
- **Compiled container cache:** delete it after upgrading, as the
  registrations change.
- **`ErrorMiddleware` is now scoped.** A singleton of yours that depended on
  it must become scoped.

Read [a company website](cookbook/website.md) for all of it at work.

## What's in 2.0

- **A strict container** that validates the whole application at startup,
  with scopes, keyed services, decorators, attributes, lazy services and a
  compiled container for production.
- **Modules** with layers, explicit dependencies, contracts between modules,
  and architecture tests that enforce the boundaries.
- **Delivery:** an HTTP kernel (PSR-7/15/17) and routing, console commands
  on Symfony Console, and background jobs. Each request, command or job runs
  in its own container scope with a correlation id.
- **Business logic:** DDD building blocks, a mediator with pipeline
  behaviours, validation and serialization.
- **Infrastructure:** database (query builder, schema builder with
  `ALTER`, migrations per module, unit of work), cache, filesystem, logging
  and events.
- **Tooling:** testing helpers and code generators.

Coming from 1.x? Read [Upgrading from 1.x](upgrading.md).

## 2.0.0 — 2026-10-26

The final release. No changes since `2.0.0-rc.1`.

## 2.0.0-rc.1

The public API has been frozen since `2.0.0-beta.1`. The release candidate
adds no features. It contains the fixes from the 2.0 security review and
from the benchmarks.

### Security

- **security:** with bcrypt, passwords longer than 72 bytes, or containing a
  NUL byte, were truncated, so two passwords sharing a 72-byte prefix
  verified against each other. They are now pre-hashed with SHA-384.
  - Argon2id, the default when PHP supports it, was not affected.
  - bcrypt hashes of such passwords made by a beta no longer verify: ask
    those users to reset their password.
- **filesystem:** `UploadedFileStore` no longer keeps extensions a web
  server could execute, such as `.php`, on stored uploads. It also checks
  the size limit against the bytes received.

### Performance

- **core:** the container cache loads with a single include served by
  OPcache, instead of reading the file. Cache files from the betas still
  load.

### Documented limits

- **Not included:** CSRF protection, CORS and security headers. Add them as
  PSR-15 middleware.
- **Cache directory:** only the application may write to it.
- **Symbolic links:** a local disk follows those inside its root.

## Betas

- **beta.3:** maker wires generated code into the module. The testing
  package adds `ModuleRules` and `HttpTestClient::submit()`.
- **beta.2:** `Schema::table()` alters existing tables.
- **beta.1:**
  - correlation ids for console commands;
  - InnoDB/utf8mb4 for MySQL;
  - `#[ErrorMessage]` for hydration errors;
  - providers for serialization and validation.

# Release notes — Zana 2.0

Zana 2.0 turns the three 1.x packages into a framework of 19 packages for
modular business applications, on PHP 8.5. Every package carries the same
version number. Each repository has its own `CHANGELOG.md`.

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

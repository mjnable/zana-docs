# Release notes — Zana 2

Zana 2.0 turns the three 1.x packages into a framework of 19 packages for
modular business applications, on PHP 8.5. Zana 2.1 adds what server-rendered
websites need, 2.2 what content sites need, and 2.3 the way to put them online.
2.4 makes production boots faster, 2.5 fixes the shared-hosting release build on Windows, 2.6 makes
errors and mail problems visible, 2.7 helps AI coding agents follow an
application's rules, 2.8 generates whole use cases, 2.9 adds an API
skeleton, 2.10 validates every command and query, 2.11 generates a whole
feature from one plan file, 2.12 runs the database on Doctrine DBAL, and
2.13 adds the transactional outbox, 2.14 idempotency keys, 2.15 a
security audit log, 2.16 second factors, 2.17 roles in an
organization or at an event, 2.18 leaner release archives, and 2.19
the same exclusion rules on every route to a live server.
Every package carries the same version number. Each repository has its
own `CHANGELOG.md`.

## 2.19.0 — Unreleased

2.19 applies the exclusion rules of 2.18 however files reach a live server
(FE-66). Each route keeps its own way of deploying. It is backward compatible
with 2.18, and nothing changes on a server until you run the new command.

- **console:**
  - `release:clean` applies the rules on the server: Git's sparse-checkout in a
    checkout, so that `git pull` keeps working, and deletion elsewhere. It
    runs only with `APP_ENV=production`, and `--dry-run` shows what would go;
  - `doctor` warns in production when these files are on the server;
  - one list of rules, used by `release:build`, `release:clean` and `doctor`.
- **maker:** `AGENTS.md` asks AI coding agents for a security check on risky
  changes: attack the code with hostile input, keep the attacks as tests, and
  report what was found and what was not covered (FE-67).
- **Docs:** [the same rules on every route](deployment.md#the-same-rules-on-every-route).

**Upgrading from 2.18:** run `composer update`. A site deployed with `git pull`
or a copied folder gets a `doctor` warning until you add
`php bin/console release:clean` to its deployment. It is only a warning. Run
`php bin/console make:agents --force`, then add back any section of your own,
so that agents get the security check.

## 2.18.0 — 2026-11-07

2.18 keeps project documents off the server (FE-65). It comes from packaging
the MMC website, whose archives held files with no use in production. It is
backward compatible with 2.17.

- **console:**
  - `release:build` leaves `README.md`, `AGENTS.md`, `CLAUDE.md` and
    `.env.example` out of the code archive;
  - `release:build --no-public` leaves `public/` out of the code archive, for
    the split layout, where `public_html.zip` is the only copy that is served.
- **Docs:** [what the archives hold](deployment.md#what-the-archives-hold),
  how to leave out your own files with `export-ignore`, and the two settings
  that point at `public_html` in the split layout.

If a server of yours uses one of these four files from the upload, keep a copy
there yourself: for example, a `.env` made from `.env.example`.

## 2.17.0 — 2026-11-06

2.17 completes phase T2 of the DRC event ticketing platform (FE-57): a user
can be the owner of one organization, a box-office agent at another, and a
scanner at one event only. It is backward compatible with 2.16. Nothing
changes until you turn the role store on.

- **security** (ADR-032):
  - `RoleAssignments` gives roles everywhere or in a `Scope`, kept in a
    table (`security.role_assignments: database`) or your own store;
  - the `Authorizer` counts the roles in the subject's scope and in the
    scopes above it (`ScopeHierarchyInterface`), with policies still
    first;
  - `#[RequiresPermission(..., scope: 'event')]` reads the scope from the
    route;
  - `RoleAssigned` and `RoleRevoked`, and the `security.role_assignments`
    check.
- **audit:** records role changes, with who made them.

See [the security guide](guides/security.md#new-in-217-roles-in-an-organization-or-at-an-event)
and [a mediator behaviour that checks every command](cookbook/recipes.md#checking-the-scope-of-every-command).

**Upgrading from 2.16:** run `composer update`. To give roles in a scope,
set `security.role_assignments` to `"database"` and add a migration that
returns `new RoleAssignmentsMigration()`.

## 2.16.0 — 2026-11-06

2.16 starts phase T2 of the DRC event ticketing platform (FE-56):
organizer staff sign in with a second factor, and guest buyers confirm a
phone number with a code. It is backward compatible with 2.15. Nothing
changes until a user provider holds second factors.

- **security** (ADR-031):
  - TOTP (RFC 6238) with recovery codes, enrolled with `TwoFactor` and
    kept by your user provider (`TwoFactorUserProviderInterface`), sealed
    under `security.two_factor.key`;
  - `SessionLogin::start()` and `completeSecondFactor()`, so a user with
    a second factor is never signed in by the password alone; remember
    this device;
  - `OneTimeCodes` for SMS or WhatsApp confirmations: they expire, work
    once, and limit attempts and issuing;
  - new events (`TwoFactorEnabled`, `TwoFactorDisabled`,
    `TwoFactorFailed`, `RecoveryCodeUsed`), and the
    `security.two_factor_key` check.
- **audit:** records the second-factor events.

See [the security guide](guides/security.md#new-in-216-second-factors).

**Upgrading from 2.15:** run `composer update`. To offer second factors,
add a nullable text column to your user table, implement
`TwoFactorUserProviderInterface`, set `security.two_factor.key`, and show
the code form when `start()` asks for it.

## 2.15.0 — 2026-11-06

2.15 completes phase T1 of the DRC event ticketing platform (FE-58): a
security audit log for disputes and the data-protection rules, fed by new
security events. It is backward compatible with 2.14. The log is off until
`audit.log.enabled` is true.

- **security:**
  - with an event dispatcher, `SessionLogin` dispatches `SignInSucceeded`,
    `SignInFailed` (wrong credentials or throttled) and `SignedOut`;
  - `PasswordResetTokens` dispatches `PasswordResetRequested` and
    `PasswordResetTokenUsed`;
  - all of them implement `SecurityEvent`.
- **audit** (ADR-030):
  - `AuditLog::record()` writes entries with the actor, the subject, the
    client address, the correlation id and the details;
  - the log is append-only, with optional hash chaining (`audit:verify`);
  - `#[Audited]` mediator commands record only the fields they list;
  - security events are recorded;
  - `audit:install-log`, `audit:prune` (optional retention), `audit:log`,
    the `audit.log` and `audit.chain` checks, and `inspect audit-log`;
  - tested on SQLite, MySQL and PostgreSQL, with four processes appending
    to one chain.

See [the audit guide](guides/audit.md#new-in-215-the-security-audit-log).

**Upgrading from 2.14:** run `composer update`. To keep a security audit
log, set `audit.log.enabled` (and `audit.log.chain` if you want hash
chaining), then run `php bin/zana audit:install-log` and
`php bin/zana migrate`.

## 2.14.0 — 2026-11-05

2.14 is the third step towards the DRC event ticketing platform (FE-61):
a new package, `mj-zana/idempotency`, so a buyer who retries "pay" on a
slow connection is charged once. It is backward compatible with 2.13.

- **idempotency** (new, ADR-029):
  - `#[Idempotent]` on a handler and `IdempotencyMiddleware` on its route:
    a request with an `Idempotency-Key` runs once, and a retry gets the
    stored response (`Idempotent-Replayed: true`);
  - 409 while the first request still runs, 422 when the key comes with
    another request, 400 when a required key is missing;
  - keys claimed atomically in the database, per signed-in user; a server
    error or an exception frees the key for a retry;
  - `idempotency:install`, `idempotency:prune`, a `check` for marked
    handlers without the middleware, and `inspect idempotency`;
  - tested on SQLite, MySQL and PostgreSQL, with four simultaneous
    processes.

See [the idempotency guide](guides/idempotency.md).

**Upgrading from 2.13:** run `composer update`. To use idempotency keys,
run `composer require mj-zana/idempotency`, register
`IdempotencyServiceProvider`, then `php bin/zana idempotency:install` and
`php bin/zana migrate`.

## 2.13.0 — 2026-11-05

2.13 is the second step towards the DRC event ticketing platform (FE-55):
a new package, `mj-zana/outbox`, so a paid order never loses its "tickets
issued" event, and a rolled-back one never sends it. It is backward
compatible with 2.12.

- **outbox** (new, ADR-028):
  - `Outbox::record()` and `add()` save events in the transaction of the
    change; `transactional()` relays them right after the commit;
  - the relay dispatches them through the event dispatcher, at least once,
    with backoff and failure marking; `outbox:relay` (scheduled or
    `--loop`) delivers what a crash or a failing listener left behind, and
    `outbox:retry` makes failed messages due again;
  - `Inbox::once()` handles each message once per consumer;
  - `outbox:install`, a `check` for waiting and failed messages, and
    `inspect outbox`;
  - tested on SQLite, MySQL and PostgreSQL.
- **queue:** fixed on PostgreSQL. A job with protected or private
  properties could not be read back, because PostgreSQL truncated its
  payload at the first NUL byte that `serialize()` writes. Payloads are
  now stored as base64, and those written by 2.12 and earlier are still
  read.

See [the outbox guide](guides/outbox.md).

**Upgrading from 2.12:** run `composer update`. To use the outbox, run
`composer require mj-zana/outbox`, register `OutboxServiceProvider`, then
`php bin/zana outbox:install` and `php bin/zana migrate`.

## 2.12.0 — 2026-11-04

2.12 is the first step towards the DRC event ticketing platform (FE-60):
the database package runs on Doctrine DBAL, keeping its API, and gains
what inventory and payments need. It is backward compatible with 2.11.

- **database:**
  - `ConnectionFactory::create()` and the provider return a `DbalConnection`:
    DBAL compiles the query and schema builders for SQLite, MySQL and
    PostgreSQL, and handles transactions and savepoints;
  - `increment()`, `decrement()`, `whereColumn()`, `upsert()` and
    `lockForUpdate()`, for a seat reservation that concurrent buyers
    cannot oversell (see [the database guide](guides/database.md#new-in-212-on-doctrine-dbal-with-counters-upserts-and-locks));
  - `DatabaseException::isRetryable()` for deadlocks;
  - tested on SQLite, MySQL and PostgreSQL 18, with concurrent processes.

**Upgrading from 2.11:** run `composer update`; `doctrine/dbal` is
installed with the database package. Nothing else changes, with three
details:
- `ConnectionFactory::create()` is declared to return
  `ConnectionInterface`, not the PDO `Connection` class.
- On MySQL, an update that changes no value reports 0 rows.
- New PostgreSQL `id()` columns are identity columns rather than
  `BIGSERIAL`; existing tables are unchanged.

`ConnectionFactory::createPdo()` keeps the 2.11 connection until 3.0.

## 2.11.0 — 2026-11-01

2.11 completes the support for AI coding agents planned in 2.7: feature
plans, an AI-ready checklist, and hints that name the application's own
console script. It is backward compatible with 2.10.

- **maker:**
  - `make:plan <file.json>` generates a module, its entities, value
    objects, repositories and features from one plan file
    (`zana.plan/1`), by running the same `make:` commands, all or nothing
    (FE-51); see the [walkthrough](ai-agents.md#a-walkthrough-a-library-api-28);
  - `check` warns when `AGENTS.md` is missing or names another Zana
    version (`agents.instructions`, FE-52);
  - `make:agents` writes the commands with the console script it was run
    with, e.g. `php bin/zana` (FE-54).
- **core:** `ConsoleScript`, which the console fills with the script that
  started it; hints say `php bin/zana migrate` there and "the console
  command `migrate`" where the script is unknown (FE-54).
- **database, communication:** the pending-migrations hint, the production
  mail warning and the test e-mail use it.
- **Docs:** an [AI-ready checklist](ai-agents.md#an-ai-ready-checklist-211)
  for applications, and [one for packages](contributing.md#an-ai-ready-checklist-for-packages-211)
  (FE-52); the walkthrough's plan file, tested against its commands.

**Upgrading from 2.10:** run `php bin/console make:agents --force` for the
2.11 `AGENTS.md`, which names `make:plan`, then add back your own section.
With `mj-zana/maker`, `check` now warns about a missing or outdated
`AGENTS.md`.

## 2.10.0 — 2026-11-01

2.10 validates commands and queries whatever sends them, and finds
middleware that would fail on every request. It is backward compatible
with 2.9.

- **validation:** `ValidationBehavior`, a mediator behaviour that validates
  every command and query before its handler runs (FE-53). It is opt-in:
  list it in `config/mediator.php`. With `mj-zana/http`, a
  `ValidationException` is a 422 with the field errors.
- **http:**
  - exception mappers: services tagged `ExceptionMapperInterface::TAG` turn
    other exceptions into HTTP errors before `ErrorMiddleware` renders them;
  - `check` creates every global middleware once, so a middleware that
    needs a service nobody registered is reported (`http.middleware`).
- **serialization:** with `mj-zana/http`, a `DenormalizationException`
  (a missing or mistyped field) is a 422 with the field errors.
- **routing:** `check` also creates every route middleware.
- **console:** a `ValidationException` is printed field by field.
- **skeleton-api:** enables `ValidationBehavior`; `user:create` relies on
  it.
- **Docs:** the clinic example uses the framework's behaviour instead of
  its own.

**Upgrading from 2.9:** nothing to change. An application with its own
validation behaviour can replace it with
`Zana\Validation\ValidationBehavior` in `config/mediator.php`. Generated
`ApiInput` classes keep validating; with the behaviour too, an HTTP request
is validated twice with the same errors.

## 2.9.0 — 2026-11-01

2.9 is phase A3 of the support for AI coding agents: a starting point for
JSON APIs. It is backward compatible with 2.8.

- **skeleton-api (new):** `composer create-project mj-zana/skeleton-api`
  gives an API with an `Accounts` module (users in the database,
  `POST /api/tokens` for a bearer token, rate-limited, `GET /api/me`, and
  `user:create`), errors in problem JSON, tests that run without `.env`,
  `AGENTS.md`, and a CI workflow that runs the tests and `check`.
- **http:** `http.json_errors` answers problem JSON to every client;
  `HttpKernel::run()` sends a `.json` fallback as problem JSON when the
  application cannot start.
- **security:** without a cache, the rate limiter fails only when used, so
  `check` and `doctor` pass in applications that do not rate-limit.
- **skeleton-web:** its CI runs `check`; `AGENTS.md` for 2.9.

**Upgrading from 2.8:** nothing to change. For an existing API built on
skeleton-web, set `http.json_errors` to `true` in `config/http.php`, and
run `php bin/console make:agents --force` for the 2.9 `AGENTS.md`.

## 2.8.0 — 2026-11-01

2.8 is phase A2 of the support for AI coding agents: the maker generates
whole use cases that pass `check`, and the documentation walks through the
workflow on a real application (see
[working with AI coding agents](ai-agents.md#a-walkthrough-a-library-api-28)).
It is backward compatible with 2.7.

- **maker:**
  - `make:feature … --field=name:type --http`: a command or query with
    typed, validated fields, its handler, an API action and its route, the
    module's `ApiInput` (400 and 422 with field errors), a handler test and
    an HTTP test;
  - `make:value-object` and `make:repository` (the database
    implementation of an entity's repository, with optimistic versions, and
    its migration); `make:entity` adds `restore()`;
  - `--format=json` on every `make:` command (`zana.make/1`): the files,
    the lines registered in the module and the manual steps;
  - the missing providers and packages are named; generated files have LF
    line endings on every system;
  - `AGENTS.md` names the new commands and plans with
    `--dry-run --format=json`.
- **skeleton-web:** runs the tests in `modules/*/Tests`, which the maker
  generates; `AGENTS.md` for 2.8.
- **Docs:** a walkthrough that builds a library API following the workflow,
  checked by rebuilding its example from the page's commands.

**Upgrading from 2.7:** run `php bin/console make:agents --force` for the
2.8 `AGENTS.md`, then add back your own section. An entity generated before
2.8 needs `restore()` before `make:repository`; the command says so. A
`command` or `query` stub copied into `stubs/` before 2.8 keeps working,
without fields.

## 2.7.0 — 2026-10-31

2.7 helps AI coding agents work on a Zana application the way its
developers do (see [working with AI coding agents](ai-agents.md)). Nothing
in it calls an AI or depends on an AI provider. It is backward compatible
with 2.6.

- **console:**
  - `inspect [section]` shows the environment, modules, services, console
    commands, configuration keys with their types (never their values),
    packages, and the sections packages add (routes, migrations);
  - `check` verifies the application: the container builds, deployment
    checks, singletons, the architecture rules on `modules/` (with
    `mj-zana/testing`), and the checks packages add. Each problem is a
    finding with an id, a severity, a message, a file and a hint;
  - `inspect`, `check` and `doctor` take `--format=json`
    (`zana.inspect/1`, `zana.check/1`);
  - `ConsoleKernel::runBuilder()` builds the application itself, so `check`
    reports a container that does not build.
- **core:** `ApplicationCheck`, `InspectionSection`, `Finding` and
  `Severity`, for packages and applications to add checks and sections.
- **routing:** a `routes` section, and a check that route handlers and
  middleware exist and are registered.
- **database:** a `migrations` section, and a check for pending migrations
  and missing migration files.
- **http:** without a PSR-16 cache, the page cache fails only when used, so
  `check` and `doctor` no longer report it in applications that do not
  cache pages.
- **maker:** `make:agents` writes `AGENTS.md`, linked to the documentation
  of the installed version, with `CLAUDE.md` and
  `.github/copilot-instructions.md` pointing to it.
- **skeleton-web:** ships `AGENTS.md` and a test that fails when it names
  another Zana version; `bin/console` uses `runBuilder()`; the `make:`
  commands are registered when `mj-zana/maker` is installed.
- **Docs:** [`llms.txt`](llms.txt), an index of these pages for agents,
  generated and tested; the full text of every page is attached to each
  documentation release as `llms-full.txt`.

**Upgrading from 2.6:** in an existing application, run
`php bin/console make:agents` (with `mj-zana/maker`). Optionally, use
`ConsoleKernel::runBuilder()` in `bin/console` as the skeleton does. The
text output of `doctor` now lists its findings the same way as `check`.

## 2.6.0 — 2026-10-31

2.6 makes failures on a new server visible. It is backward compatible with
2.5.

- **http:** without a PSR-3 logger, a server error goes to PHP's error
  log, on one line with the exception chain and no stack trace.
- **logging:**
  - log files rotate (`max_size`, `max_files`);
  - a file that cannot be written no longer breaks the application: the
    line goes to PHP's error log;
  - a deployment check warns when a log file cannot be written.
- **communication:**
  - `mail:test <email>` prints the mail server's answer, passwords hidden;
  - a deployment check warns when production sends no email (`memory://`
    or `null://`).
- **skeleton-web:** logs warnings and errors to `var/log/app.log`.
- **Docs:** the website's contact form survives a mail failure.

## 2.5.1 — 2026-10-27

A fix for `.env` values (FE-42), backward compatible except for the
conversion changes below.

- **configuration:** a quoted `.env` value is always text:
  `APP_SHOP__WHATSAPP="+243812345678"` is no longer the float
  `243812345678.0`, and an all-digit password no longer stops the
  application with "should be a string, got float". Unquoted values with a
  leading `+` or `0`, or more than 15 digits, stay text too. `string()`
  accepts integers, `bool()` accepts `"true"`, `"false"`, `"1"` and `"0"`, and
  `#[Config]` injection converts to the parameter's type.

**Upgrading from 2.5.0:** an unquoted value with a leading zero, such as
`APP_SHOP__CODE=007`, used to be the integer 7 and is now the text "007";
`int()` still reads it as 7. Values that are quoted and read with `get()`
are now text: read them with the typed method you expect.

## 2.5.0 — 2026-10-27

2.5 is backward compatible with 2.4.

- **console:** `release:build` moves here from the website skeleton
  (ADR-021), so every site gets its fixes with `composer update`. It no
  longer hangs on Windows when Composer writes a lot (the output goes to a
  file instead of pipes), finds Composer on Windows by itself, shows the
  output with `-v`, and stops after `--timeout`.
- **skeleton-web:** uses the console package's command instead of its own
  copy.

**Upgrading from 2.4:** in a project created from the skeleton 2.3 or 2.4,
delete `src/Release/`, `src/Console/ReleaseBuildCommand.php` and its
registration in `src/Bootstrap.php`. Until then, the project's own copy
replaces the package's command.

## 2.4.0 — 2026-10-26

2.4 is backward compatible with 2.3, except for the first two points under
"Upgrading" below.

- **core:** a configuration cache for production
  (`withConfigurationCache()`). A boot reads one file instead of scanning
  `config/` and loading each file; on the benchmark application, a boot plus
  one request takes about a third of the time with five configuration
  files. `.env`, `APP_*` variables and overrides still apply at every boot.
- **console:** `cache:clear` and `cache:warm` handle the configuration
  cache; `about` and `doctor` report it.
- **testing:** `check()`, `uncheck()` and `choose()` tick checkboxes and
  radio buttons in form tests, and `fill()` takes a list for a `name[]`
  group. A box or value the page does not have fails the test.
- **container:** factories declare the services they resolve with
  `->uses()`, and the build checks them like constructor parameters: a
  singleton factory using a scoped service now fails at startup. Without the
  declaration, the error at the first resolution names both services. Every
  Zana package declares its factories.
- **security:** `PasswordResetTokens` is scoped, so the user provider may be
  scoped.
- **console:** `doctor` creates every singleton once.
- **skeleton-web:** uses the configuration cache.

**Upgrading from 2.3:**

- A singleton of yours that receives `PasswordResetTokens` must become
  scoped or transient; the build names it.
- A class of yours that implements `ServiceCollectionInterface` must add
  `uses()`.
- To use the configuration cache, add
  `withConfigurationCache()` next to `withContainerCache()`, move secrets
  read with `$_ENV` or `getenv()` in configuration files to `APP_*`
  variables, and run `cache:clear` on each deployment.

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

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
organization or at an event, 2.18 leaner release archives, 2.19
the same exclusion rules on every route to a live server, 2.20
closes the security audit and adds what request input needs in queries,
2.21 puts security rules in `check`, starting with: every route says
who may reach it, 2.22 attacks every route to see what it does, and 2.23
records who may reach each route, so every change is reviewed, 2.24
reads what spam a form was sent, 2.25 asks a CAPTCHA provider, 2.26
makes limits hold under concurrent requests, 2.27 tests that one user's
records stay theirs, 2.28 closes three documented limits, 2.29 the
spam rules' race, 2.30 adds a shared kernel, 2.31 sets the
known-device cookie for you, 2.32 answers cross-origin requests, and
2.33 closes two more documented limits.
Every package carries the same version number. Each repository has its
own `CHANGELOG.md`.

## 2.33.0 — Unreleased

Two documented limits, now settings.

- **filesystem:** `'follow_links' => false` on a local disk refuses every
  path through a symbolic link, a Windows junction included, and leaves
  links out of listings. For the disks where users' uploads land.
- **view:** `safe_url($url, '#', ['example.com', '*.example.com'])`, or
  `view.safe_url.hosts` for every call, keeps a link to another site only
  when its host is listed, read as a browser reads it.

Both are off by default: nothing changes until they are set. See
[filesystem](guides/filesystem.md#rules-worth-knowing) and
[views](guides/view.md).

**Upgrading from 2.32:** `composer update`.

## 2.32.0 — 2026-11-21

Front ends on other sites, with CORS.

- **http:** `CorsMiddleware` and `http.cors`: the sites listed may read the
  answers from their pages, errors included; a preflight is answered before
  routing, 204 for what is listed and 403 otherwise. Exact origins, or
  `https://*.example.com` for subdomains; `*` only without credentials;
  `null` never. `check` reports each mistake, and a wrong entry allows less,
  never more.
- **API skeleton:** `CorsMiddleware` in `http.middleware`, with no site
  listed.

See [http](guides/http.md#new-in-232-cross-origin-requests-cors).

**Upgrading from 2.31:** `composer update`. An API called from a browser on
another site: list that site in `http.cors.origins`, and add
`CorsMiddleware` to `http.middleware`.

## 2.31.0 — 2026-11-21

The account lockout's whole protection, with one middleware.

- **security:** `KnownDeviceMiddleware` reads and sets the "known device"
  cookie of 2.28 around the sign-in routes, so a browser that signed in
  before gets in while others are locked out of its login, with no change
  to the sign-in controller. Put it before `SessionMiddleware`.

See [security](guides/security.md#known-devices).

**Upgrading from 2.30:** `composer update`, then add
`KnownDeviceMiddleware` to the routes that sign in.

## 2.30.0 — 2026-11-21

A shared kernel that every module may use.

- **testing, console:** declare one module's `Contracts` as the shared
  kernel in `config/architecture.php` (`shared_kernel`), and every layer of
  every module may use its values, Domain and Contracts included: money, a
  clock, codes. The kernel itself may use only plain PHP and `zana/ddd`.
  `check` reads the setting, and so does `ModuleRules::forApplication()` in
  an application's own test.
- **Skeletons:** `config/architecture.php`, with the setting commented out;
  `AGENTS.md` says what belongs in the kernel.
- **Docs:** where tests across modules go.

See [modules](cookbook/modules.md#new-in-230-a-shared-kernel).

**Upgrading from 2.29:** `composer update`. Without the setting, the rules
are those of before.

## 2.29.1 — 2026-11-21

Found by asking the CAPTCHA providers' real endpoints, with their test keys:

- **captcha:** Cloudflare answers a wrong or missing Turnstile secret with
  HTTP 400. That was taken for an outage, so with
  `security.bot.on_unavailable: 'allow'`, every request got through. Now it
  is a misconfiguration, refused and logged as an error, whatever the status.
- **captcha:** `LiveProvidersTest` asks Turnstile, reCAPTCHA and hCaptcha
  with their published test keys, opt-in (`ZANA_TEST_CAPTCHA_LIVE=1`), and
  in the package's daily CI run.
- **Docs:** the providers' test keys, the hostnames their answers name, and
  why a wrong reCAPTCHA secret looks like a bot
  ([security](guides/security.md#new-in-225-bot-verification)).

**Upgrading from 2.29.0:** `composer update`. With Turnstile and
`on_unavailable: 'allow'`, check that the secret is set in production.

## 2.29.0 — 2026-11-21

The spam rules' last race, closed.

- **security:** the rule for repeated text claims a post in one step on a
  Zana cache, so identical posts sent at the very same instant pass once:
  of twenty at once, as many as all twenty passed before. An application's
  own rule can do the same with `ClaimingSpamRuleInterface`; rules written
  for 2.25 work as before.

See [security](guides/security.md#new-in-225-bot-verification).

**Upgrading from 2.28:** `composer update`.

## 2.28.0 — 2026-11-21

Three limits that were documented, closed.

- **cache:** file entries are signed, and only a file the application
  signed is unserialized, so a file planted in the cache directory can no
  longer run code. The key is `cache.key`, or one derived from
  `security.secret`; a cache from before 2.28 fills again.
- **security:** a guesser locks out only their own address, not the
  account's owner: five tries per login per address, and ten times as many
  for the login from every address. Each sign-in gives a "known device"
  token, which lets that browser in while others are locked out.
- **security:** with `security.csrf.bind_to_session`, a visitor who has a
  session gets CSRF tokens tied to it, so another visitor's token no longer
  passes. Off by default.

See [cache](guides/cache.md#new-in-228-signed-cache-files) and
[security](guides/security.md#new-in-228-lockouts-known-devices-and-tokens-tied-to-a-session).

**Upgrading from 2.27:** `composer update`. Set `security.secret` or
`cache.key` if `check` warns about the cache. To use known devices, set the
cookie from `LoginResult::$knownDevice` and pass it to `start()`.

## 2.27.1 — 2026-11-21

- **database:** a SQLite database's folder is made when it is missing. On a
  fresh API skeleton there is no `var/`, so the first
  `php bin/console migrate` failed with "unable to open database file". A
  folder that cannot be made is named in the error.

**Upgrading from 2.27.0:** `composer update`.

## 2.27.0 — 2026-11-21

Tests that one user cannot reach another's records.

- **testing:** `assertRecordsStayWithTheirOwners()`. Say how to sign in two
  users and how to make a record of the first one's; every route with that
  record's parameter is then tried as both, on a fresh record each time. The
  other user must be refused (401, 403 or 404), and the owner must get
  through, so the refusal is known to be about the owner. Writes too, with
  valid bodies, as forms or as JSON for an API.

See [testing](guides/testing.md#new-in-227-one-users-records-stay-theirs).

**Upgrading from 2.26:** `composer update`. Nothing changes until a test
calls the new assertion.

## 2.26.0 — 2026-11-20

Limits that hold when requests arrive at the same instant.

- **security:** rate limits and single-use CSRF tokens read a value and
  then wrote it, so concurrent requests all passed: twenty at once passed a
  limit of five, including the limit on sign-in attempts. On a Zana cache
  they now count and claim in one step. `check` warns when the application's
  cache cannot (`security.atomic_cache`).
- **cache:** `add()` and `increment()`, atomic on every Zana store
  (`AtomicCacheInterface`); the file store locks the key while it reads and
  writes.

See [security](guides/security.md#new-in-226-limits-that-hold-under-concurrent-requests)
and [cache](guides/cache.md#new-in-226-adding-and-counting-atomically).

**Upgrading from 2.25:** `composer update`. With a cache other than
mj-zana/cache's, read what `check` says.

## 2.25.1 — 2026-11-15

The owner's review of FE-64, each point checked by running 2.25.0:

- **security:** a misconfiguration (no or a wrong secret, no HTTP client, no
  provider, a verifier that throws) is always refused, whatever
  `security.bot.on_unavailable` says; `allow` rides out a provider's outage
  only. The three kinds of failure are logged apart (error, warning,
  notice), and never with the token: a verifier's exception message is no
  longer logged at all, since a custom one could hold the token.
- **security:** expectations are strict: an expected action or hostname
  that a provider reports but does not return is refused. `check` warns
  where a provider cannot report what is expected. Verifiers written for
  2.25.0 keep working, and are held to both.
- **captcha:** reCAPTCHA's sources as Google documents them, with
  `connect-src` and its second frame host; and `check` reads sources scoped
  to a path, such as `https://www.google.com/recaptcha/`, which 2.25.0
  wrongly reported as blocked.
- **Docs:** bound the HTTP client (a timeout, a total duration, no retries).

**Upgrading from 2.25.0:** `composer update`. Bound your HTTP client. If you
set `#[VerifyHuman(action: …)]` with reCAPTCHA v2 or hCaptcha, `check` now
says the action cannot be enforced.

## 2.25.0 — 2026-11-14

FE-64: bot verification, for a form attacked despite the other defences
and for purchases bots race people for. Security holds the rule; the new
mj-zana/captcha package holds the providers.

- **security:** `#[VerifyHuman(action, minimumScore)]` and
  `BotVerificationMiddleware` ask a `BotVerifierInterface` about the token
  before the handler runs. A provider that cannot be asked refuses the post,
  unless `security.bot.on_unavailable` allows it. `FakeBotVerifier` for
  tests. Refusals are recorded, never the token.
- **captcha (new):** Cloudflare Turnstile, reCAPTCHA v2 and v3, hCaptcha,
  chosen by `captcha.provider`, asked through a PSR-18 client the
  application installs; `captcha_widget()` prints the widget with the CSP
  nonce.
- **`check`:** an attribute nothing enforces, no verifier, a verifier that
  asks nobody in production, no provider, secret or HTTP client, and a
  Content-Security-Policy that would block the widget (the default does),
  which would refuse every visitor.
- **Spam rules:** a repeat sent while the first is being handled is caught;
  `audit:spam` summarises refusals by form and rule.
- **http:** `SecurityHeadersMiddleware::directives()`, the policy as sent.

See [security](guides/security.md#new-in-225-bot-verification).

**Upgrading from 2.24:** nothing changes until a handler asks for
verification. To protect one, `composer require mj-zana/captcha
symfony/http-client nyholm/psr7`, register the client, set
`captcha.provider`, and run `php bin/console check`.

## 2.24.1 — 2026-11-14

Two fixes to the spam rules of 2.24, found when the owner reviewed them, and
a word from `check` on how long audit entries are kept.

- **security:** an application's own spam rule could put the visitor's text
  in the audit log through its reason. Such rules are now recorded by name
  and points only; Zana's rules keep their reasons, which hold no text.
- **security:** a cache that fails no longer turns a received message into
  an error for the visitor, nor refuses a real one: the repeat rule finds
  nothing for that post, the other rules still apply, and it is logged.
- **audit:** `check` warns when the audit log is on and
  `audit.log.retention_days` is not set, so keeping every entry forever is
  a choice; a value `audit:prune` cannot use is an error. See
  [choosing the period](guides/audit.md#new-in-215-the-security-audit-log).

**Upgrading from 2.24.0:** `composer update`. With the audit log on and no
retention set, `check` now warns: set `audit.log.retention_days`.

## 2.24.0 — 2026-11-14

FE-63: spam rules per form, beside the honeypot and the time trap, for spam
that people type or that bots send slowly.

- **security:** `#[SpamRules('contact')]` and `SpamGuardMiddleware`, with the
  rules in `security.spam.forms.contact`: `max_links`, `blocked_terms`
  (whole words, whatever the case, spacing or invisible characters),
  `duplicate_minutes` (the same text again, with a fresh token), your own
  `rules`, a `threshold`. Spam is treated as a filled honeypot: sent to the
  form's `redirect` as if it had succeeded, or refused with 403.
- **Who was refused, and why:** `SpamRefused`, recorded by the audit log as
  `security.spam_refused`, with the rules that found something, never the
  text.
- **`check`:** `security.spam_rules` reports settings that cannot be used and
  rules nothing enforces.
- **`AGENTS.md`:** a public form that sends a message to people gets a rate
  limit and spam rules, and answers a validation error with 422.

See [security](guides/security.md#new-in-224-spam-rules).

**Upgrading from 2.23:** nothing changes until a form names its rules. To
protect one, add `#[SpamRules]`, `SpamGuardMiddleware` on its route and its
settings, then run `php bin/console check`.

## 2.23.0 — 2026-11-14

Layer 4 of the security verification (ADR-033), the last: a committed
baseline of who may reach each route, so a new route, or one whose access
changes, is reviewed by a person.

- **security:** `security/exposure.json`, one line a route: `public` with
  its reason, `user`, `permission` with the permissions, or `anyone` with
  the problem, for a route nothing protects. Deterministic: sorted, and
  nothing from the environment or the machine.
- **Showing and recording are separate.** `php bin/console
  security:exposure` shows what differs and writes nothing, exiting with 1;
  `--init` records the first baseline, `--update` the reviewed changes.
  Running the command again never approves a change.
- **`check`** compares and never writes: `security.exposure` reports a route
  that is new or whose access changed, including a permission changed on a
  route that stays protected, as an error with `security.strict`, so CI and
  `release:build` stop. Without the baseline, a warning, in strict mode too:
  an upgrade fails nothing.
- **Integrity:** only a baseline the command writes is accepted. A hand
  edit, a route named twice, or an unknown access fails in strict mode.
- **Skeletons:** both ship their `security/exposure.json`.
- **`AGENTS.md`:** an agent shows the changes and names each in its
  summary; it never records them.

See [security](guides/security.md#new-in-223-the-reviewed-list-of-what-is-exposed).

**Upgrading from 2.22:**

- Run `php bin/console security:exposure`, review who may reach each route,
  then `php bin/console security:exposure --init`, and commit
  `security/exposure.json`. Each route open to `anyone` is one `check`
  already reports.
- Run `php bin/console make:agents --force`, then add back any section of
  your own.

## 2.22.0 — 2026-11-14

Layer 2 of the security verification (ADR-033): `check` reads what each
route declares, and now a test attacks each route to see what it does.

- **testing:** `assertRoutesWithstandAttacks()` attacks every route in
  memory, so a new route is attacked as soon as it exists:
  - a route that says it needs a user gets an anonymous request and an
    expired session, which must not reach its handler. This tests your own
    sign-in middleware declared in `security.access_middleware` too;
  - a public form behind `CsrfMiddleware` gets no token, a forged one and a
    cross-site `Origin`;
  - a route with `#[RateLimit]` gets one request more than it allows;
  - every route anyone may reach gets script, SQL, template, traversal, NUL
    and long values, a list where a value is expected, a body that is not
    JSON and a 2 MB body. A server error, a PHP warning in your code, or a
    page printing the script as sent fails.
- **As a signed-in user too:** with `signIn:` (how your users sign in) and
  `holding:` (the permissions that user holds), a route that needs another
  permission must refuse them, and the routes they may reach get the CSRF
  attacks and the hostile input as that user. A sign-in that gets nobody
  in, or a `holding` that names a permission the user lacks, fails, so the
  attacks cannot pass by proving nothing.
- **The access attacks write nothing:** a probe, the route's last
  middleware, answers instead of the handler.
- **Skeletons:** `tests/SecurityTest.php` runs it, so CI fails when a route
  does not withstand an attack. The API skeleton signs in a user without
  roles with a token.
- **security:** `RouteAccess`, who may reach one route, read alike by `check`
  and by the attacks.

**Limits to know:**

- **One user reading another's data is not attacked.** Which records belong
  to whom is your application's knowledge: test it with two users of your
  own.
- **The signed-in attacks are as good as the sign-in you give them,** and
  CSRF is only tried with a session, not with a bearer token.
- **Sample values:** parameters get values their patterns accept. A route
  whose pattern accepts none is reported as not attacked, not as passed.
- **The hostile-input attacks run handlers,** as a visitor could: run them on
  the test database.

**Upgrading from 2.21:**

- Add a `tests/SecurityTest.php` like the skeletons' to your application
  ([testing](guides/testing.md#new-in-222-every-route-attacked)). Expect
  findings on the first run; each names the route and the attack.
- Run `php bin/console make:agents --force`, then add back any section of
  your own.

## 2.21.0 — 2026-11-14

Security verification becomes part of every build, not only of reviews
(ADR-033, layers 1 and 3). `check` gains security rules, the skeletons make
them errors, and `release:build` refuses to build while one fails. Nothing
fails in an existing application on `composer update`: the rules are
warnings until you turn on `security.strict`.

- **Deny by default:** every route says who may reach it, with
  `#[RequiresAuthentication]`, `#[RequiresPermission]`, or the new
  `#[PublicRoute(reason: '…')]` (on a handler, a class, a closure, or a
  whole group with `public:`). `check` reports a route that says nothing.
- **Rules nobody enforces:** an access attribute without
  `AuthorizationMiddleware` on its route, and `AuthorizationMiddleware` or
  `RateLimitMiddleware` placed in `http.middleware`, where they run before
  routing and enforce nothing.
- **Your own sign-in:** an application that signs users in its own way, such
  as a website in front of an API, declares its middleware in
  `security.access_middleware` with how it refuses anonymous visitors. Its
  routes then count as needing a user, listed apart from Zana's own
  protection; it never counts as a permission check.
- **Forms:** a route that changes data with session sign-in and no CSRF
  protection; a public one without a rate limit (a warning).
- **Uploads and templates:** a disk inside `public/` (a warning), and
  `view.unescaped_object`, an error in strict mode.
- **The gate:** `security.strict: true` makes the rules errors in `check`,
  and `release:build` refuses to build on them. Both skeletons turn it on,
  and their CI runs `composer audit`.
- **Generators:** pages are public, API actions need a signed-in user, and
  `--public="reason"` makes one public. The generated API test proves an
  anonymous request is refused.
- **`AGENTS.md`:** every route says who may reach it, and `#[PublicRoute]`
  is never added only to silence `check`.

**Limits to know:**

- **Authentication middleware is not protection.** It identifies a user
  when there is one, and lets anonymous requests through. Only an access
  attribute enforced by `AuthorizationMiddleware` closes a route.
- **`check` cannot judge business logic,** such as whether one user may see
  another's invoice. Your policies, your tests and the security step of
  `AGENTS.md` cover that.
- **A declared reason is trusted.** `#[PublicRoute]` with a poor reason
  passes, and so does a declared sign-in middleware that does not really
  refuse anonymous visitors: each reason is there to be reviewed. Layer 2,
  in 2.22, will test them by sending anonymous requests to every route.

**Upgrading from 2.20:**

- Run `check`: each `security.*` warning names a route or a setting to fix.
  When none is left, set `security.strict` to `true` in
  `config/security.php`, so that a new one fails CI.
- Give your public routes a reason, e.g. `$router->group('', $routes,
  public: 'The public website')` for a website's pages.
- If `AuthorizationMiddleware` is in `http.middleware`, move it to the
  routes or groups that need it: there it never ran.
- Add `- run: composer audit` after `composer install` in your CI.
- Run `php bin/console make:agents --force`, then add back any section of
  your own.

## 2.20.0 — 2026-11-14

The last release from the security audit of the 2.1 to 2.18 code: its
remaining findings are fixed, with one found while testing them. Queries
gain safe ways to use request input, grouped conditions and CHECK
constraints. Upgrade with `composer update`, then read the upgrading notes
below: queued jobs and outbox messages written before 2.20 are refused
until you let them drain.

- **queue, outbox:** jobs and messages are signed, and the signature is
  checked before anything is unserialized, so a row written to their
  tables by anything but the application cannot make a worker build an
  arbitrary class.
- **audit:** `audit.log.key` keys the hash chain, so someone who can only
  write to the database cannot rewrite the log undetected.
- **database:**
  - `whereLike()` with `LikeMatch`, where a user's `%` and `_` are plain
    characters on every database;
  - `orderByAllowed()` and `selectAllowed()` for a sort or a field list
    from the request;
  - an array or an object is no longer bound as the text `Array`;
  - grouped `where(Closure)`, `check()` constraints and `smallInteger()`;
  - TLS settings for PostgreSQL and MySQL connections, and a `check` for
    unencrypted connections to another host;
  - `scalar()` keeps a false value false; SQLite quotes names with
    backticks, so an unknown column fails as elsewhere.
- **security:** two sign-ins at the same moment cannot reuse a recovery
  code (`TwoFactorStateSwapInterface`); the CSRF check compares the
  `Origin` port; role scopes are compared by type and id.
- **view:** `safe_url()` for URLs users gave, and a `check` warning for
  templates that print an object without escaping it.
- **http:** `http.page_cache.query` limits the query parameters in a page
  cache key.
- **idempotency:** an anonymous client's keys belong to its session, or to
  its address.
- **core:** the container cache is used in production only, so a changed
  constructor works at once in development; `withoutDotEnv()` for tests
  that build production.
- **Skeletons:** `composer serve` no longer stops after 300 seconds and
  listens on `127.0.0.1` (8080 for the website, 8000 for the API); the
  API's CI runs `check` on a migrated database.
- **maker:** `AGENTS.md` names `safe_url()`, `only()` and the allow-list
  query methods.
- **Static analysis:** database reads are `@phpstan-impure`, and
  `send()` declares that it rethrows the handler's exceptions.

**Limits to know:**

- **The drain window:** while `queue.accept_unsigned` or
  `outbox.accept_unsigned` is on, unsigned rows are trusted as before 2.20,
  so a row written by anything but the application can again build any
  loaded class. Keep it on only until the old jobs and messages are gone;
  `check` warns while it is.
- **Copied jobs:** the signature covers a job or message, not its row.
  Someone who can write to the table can copy a signed one, which then runs
  again. They cannot create or change one. The outbox inbox skips a copied
  message for handlers that use it.
- **`safe_url()` checks the scheme, not the host:**
  `https://other.example` and `//other.example` are kept, since they cannot
  run script. Check the host yourself where a link must stay on your site.
- **The keyed audit chain** stops someone who can only write to the
  database. Whoever can read the key as well can still rewrite the log.

**Upgrading from 2.19.2:**

- **Queued jobs and outbox messages written before the upgrade** are
  unsigned, and refused once a key is set (and one is, whenever
  `security.secret` is). Before deploying, either empty the queue and the
  outbox, or set `queue.accept_unsigned` and `outbox.accept_unsigned` to
  `true`, deploy, let them drain, then remove both. `check` warns while
  they are on.
- **Audit log with a chain:** generate `audit.log.key` (at least 32
  characters) and set it in the server's environment. If the log already
  has entries, set `audit.log.unkeyed_until` to the newest entry's
  sequence at that moment, or `audit:verify` reports the first old entry.
- **Second factors:** implement `TwoFactorStateSwapInterface` on your user
  provider ([security](guides/security.md#new-in-216-second-factors)).
  Without it nothing breaks, but the race stays.
- **Idempotency:** an anonymous retry that spans the upgrade runs again,
  because its key is now stored under its session or address.
- **PostgreSQL booleans through `scalar()`:** a false value is now `false`,
  not `null`. Code that tested `=== null` for "no" must test `=== false`.
- **SQLite migrations:** `table()` now refuses to add an index or drop a
  column on a table with CHECK constraints or triggers, which the rebuild
  would drop. Recreate the table in that migration instead.
- **Code that passed an array as a query value,** such as a form field,
  now gets a `DatabaseException`, where `Array` used to be stored.
- **The development container cache is no longer written.** If you relied
  on it outside production, pass `always: true` to
  `withContainerCache()`.
- **Skeleton files are yours.** To take the new `composer serve` script,
  the CI step and the cache comment, copy them from the skeleton of
  2.20.0. The serve script moves to `127.0.0.1`: update `app.url` in
  `.env` if it named `localhost`.
- Run `php bin/console make:agents --force`, then add back any section of
  your own.
- Run `check`, and fix what `view.unescaped_object` and `database.tls`
  report.

## 2.19.2 — 2026-11-07

The second security release from the audit of the 2.1 to 2.18 code, for
proxies, uploads and the page cache. Upgrade with `composer update`.

- **http:**
  - only one forwarding header is read: X-Forwarded-For by default, or
    `Forwarded` with `http.trusted_proxies_header: forwarded`;
  - the scheme and port come from the entry for the visitor's hop;
  - a range in `http.trusted_proxies` that cannot be read matches nothing,
    and `check` reports it;
  - pages with a CSP nonce are not cached.
- **filesystem:** uploads drop extensions a browser would run (`html`,
  `svg`, `js`, …) unless you pass `trustedExtensions`, and a dangerous
  extension gives way to the detected type's.
- **security:** a token with a colon in its subject, such as a back-office
  preview link, no longer skips the CSRF checks.
- **maker:** `AGENTS.md` says that templates do not escape objects, and how
  to print URLs and store uploads.
- **Docs:** what the audit chain proves, why idempotency keys must be
  random, and why each query string is a page cache entry.

**Upgrading from 2.19.1:**

- Behind a proxy that sets only `Forwarded`, set
  `http.trusted_proxies_header` to `forwarded`, or every visitor shares the
  proxy's address.
- Run `check`. A range it reports used to trust every address.
- To keep `.svg` uploads, pass `trustedExtensions: ['svg']`, and serve them
  from another domain.
- Templates that print objects: use `$this->e()`.
- Run `php bin/console make:agents --force`, then add back any section of
  your own.

## 2.19.1 — 2026-11-07

A security release for sign-in and sessions, from the audit of the 2.1 to
2.18 security code. Upgrade with `composer update`. Only `mj-zana/security`,
`mj-zana/session` and the API skeleton change.

- **security:**
  - a stored second factor that cannot be opened, because
    `security.two_factor.key` changed or the state was damaged, no longer
    lets the password alone sign in;
  - changing a password signs out the account's other sessions;
  - only a valid bearer token skips the CSRF checks and the bot traps;
  - the sign-in limit per address counts failed attempts only, and applies
    only when an address is given.
- **session:** a request still running at sign-out no longer brings the
  session back.
- **API skeleton:** the token endpoint takes as long for an unknown address
  as for a known one.
- **Docs:** the back-office cookbook's check of `?to=` refuses more ways of
  naming another site.

**Upgrading from 2.19.0:**

- Everyone signed in through `SessionLogin` signs in again once.
- An application that sends an `Authorization` header other than a bearer
  token to a route with `CsrfMiddleware` now needs a CSRF token there.
- **API projects** created from the skeleton have their own copy of the
  token handler: check a password for an unknown address too, as in
  `IssueTokenHandler` of the skeleton 2.19.1.
- **Login pages** that copied the cookbook's `?to=` check: use the new one.

## 2.19.0 — 2026-11-07

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

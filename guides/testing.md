# Testing

`mj-zana/testing` tests Zana applications with PHPUnit. It builds the
application for each test, sends HTTP requests and runs commands in memory,
records events, and enforces architecture rules.

```
composer require --dev mj-zana/testing
```

## Application tests

`ApplicationTestCase` builds your application before each test and
terminates it after. This is the test from
[getting started](../getting-started.md):

<!-- example: examples/getting-started/hello/tests/GreetingTest.php -->
```php
<?php

declare(strict_types=1);

namespace Hello\Tests;

use Hello\Bootstrap;
use Zana\Core\ApplicationInterface;
use Zana\Testing\ApplicationTestCase;

final class GreetingTest extends ApplicationTestCase
{
    protected function createApplication(): ApplicationInterface
    {
        return Bootstrap::builder(dirname(__DIR__))->withEnvironment('testing')->build();
    }

    public function testTheGreetingOverHttp(): void
    {
        $this->http()->get('/hello')->assertOk()->assertJson(['message' => 'Hello, world!']);
        $this->http()->get('/hello/Ada')->assertOk()->assertJsonPath('message', 'Hello, Ada!');
    }

    public function testTheGreetingFromTheCommandLine(): void
    {
        $this->console()->run('greet Grace')->assertSuccessful()->assertOutputContains('Hello, Grace!');
    }
}
```

- **Helpers:** `app()`, `service(Some::class)`, `http()` and `console()`.
- **The HTTP client** goes straight through the kernel, with no server.
  - **Bodies:** an array body is sent as JSON, and API responses are
    accepted as JSON by default.
  - **Forms:** `submit('/patients', ['name' => 'Ada'])` posts an HTML form.
  - **Headers:** `withHeader()` and `withToken()` return a new client.
- **Responses:** `TestResponse` has `status()`, `header()`, `body()`,
  `json()` and `jsonPath('data.items.0.id')`. Its assertions cover the
  common statuses, headers, JSON subsets (`assertJson`) and paths.
- **Commands:** `console()->run('greet Ada')` returns the exit code and the
  output, with `assertSuccessful()`, `assertFailed()` and
  `assertOutputContains()`.
- **Events:** register an `EventRecorder` as the event dispatcher. It can
  wrap the real one. Then assert with `assertDispatched(Event::class, fn,
  times)` and `assertNotDispatched()`.

## Architecture rules

`ModuleRules` applies Zana's dependency rules to every module in a folder,
including modules added later:

<!-- example: examples/getting-started/hello/tests/ArchitectureTest.php -->
```php
<?php

declare(strict_types=1);

namespace Hello\Tests;

use PHPUnit\Framework\TestCase;
use Zana\Testing\Architecture\ArchitectureAssertions;
use Zana\Testing\Architecture\ArchitectureRule;
use Zana\Testing\Architecture\ModuleRules;

final class ArchitectureTest extends TestCase
{
    use ArchitectureAssertions;

    public function testEveryModuleFollowsTheDependencyRules(): void
    {
        self::assertModuleArchitecture(ModuleRules::for(dirname(__DIR__) . '/modules'));
    }

    public function testTheApplicationCodeStaysOutOfTheModules(): void
    {
        self::assertArchitecture(
            ArchitectureRule::classesIn('Hello')->mustNotDependOn('Modules'),
            dirname(__DIR__) . '/src',
        );
    }
}
```

- **Inside each module:**
  - the domain uses only zana/ddd;
  - the application uses its domain, module contracts, ddd, mediator,
    validation, serialization attributes and PSR log and event interfaces;
  - infrastructure never uses presentation;
  - presentation never uses the domain or infrastructure.
- **Between modules:** only `Contracts` may be used.
- **Extending:** `->allow('Domain', 'Brick\Money')` lets a layer use more.
- **A shared kernel (2.30):** `->sharedKernel('Modules\Shared\Contracts')`,
  or `ModuleRules::forApplication($app)`, which reads
  `architecture.shared_kernel` as `check` does; see
  [modules](../cookbook/modules.md#new-in-230-a-shared-kernel).
- **Reports:** a violation names the file, the line, the dependency and
  the rule.
- **Other rules:** `ArchitectureRule::classesIn(...)` with
  `mayDependOnlyOn()` or `mustNotDependOn()` expresses any rule of your
  own.

## Rules worth knowing

- **Use a testing environment.** Build with `withEnvironment('testing')`,
  and put overrides in `config/testing/`, e.g. an array log handler.
- **Give each test its own database.** For example, a temporary SQLite
  file set through `APP_DATABASE__CONNECTIONS__SQLITE__DATABASE`, migrated
  in `createApplication()`. The [cookbook](../cookbook/modules.md#6-testing-by-layer)
  shows the pattern.
- **Call the parents.** If you override `setUp()` or `tearDown()`, call
  `parent::` so the application is built and terminated.

## New in 2.1: websites

```php
$this->browser()->get('/en/about')->assertOk()->assertSelector('h1', 'About us');

$this->browser()->form('/en/contact')->fill(['name' => 'Ada'])->submit()->assertRedirect('/en/contact/thanks');
$this->assertMailSent(fn (Message $m): bool => $m->subject === 'Website message from Ada');
```

- **`browser()`** asks for HTML.
- **HTML assertions:** `assertSee()` and `assertDontSee()` compare escaped
  text. `assertSelector()` takes CSS selectors. `assertRedirect()` checks a
  redirect.
- **Forms:** `form()` reads a page's form with its hidden fields, CSRF
  token included.
- **Configuration per test:** implement `createApplicationBuilder()`, then
  `$this->withConfig([...])` rebuilds the application for one test.
- **No leaks from `.env`:** in the testing environment, `.env.testing`
  replaces `.env` when it exists. A test that builds another environment,
  such as production, reads `.env` unless it calls `withoutDotEnv()`
  (2.20, see [core](core.md#new-in-220)).

The [website cookbook](../cookbook/website.md) tests a whole site this way.

## New in 2.4: checkboxes and radio buttons

Form tests tick boxes and choose radio buttons as a visitor does:

```php
$this->browser()->form('/en/quote')
    ->fill(['name' => 'Ada', 'needs[]' => ['website', 'hosting']])   // a list for a name[] group
    ->check('consent')                                           // the only box of that name
    ->uncheck('needs[]', 'hosting')
    ->choose('budget', 'medium')                                // the others of the group are unselected
    ->submit()
    ->assertRedirect('/en/quote/thanks');
```

- **The page decides.** A form knows every checkbox and radio button on
  the page, ticked or not, with the values it offers (`$form->choices`).
  Ticking a box, a name or a value the page does not have fails the test,
  so a typo in a field name is caught.
- **For a group** (`needs[]`, or several boxes of one name), say which
  value; for a single box, the value is optional.
- Unticked boxes are not sent, as in a browser.

## In CI: `check` on a migrated database (2.20)

The skeletons' CI runs the tests, then `check`. The tests use a database
in memory, so each one starts fresh. But every console command also starts
with an empty memory database, so `check` there sees no table: it warns
that each migration "has not run", and the audit log check fails. Run
`check` on a migrated file instead, the schema the application will have:

```yaml
- name: check, on a migrated database
  env:
    APP_ENV: testing
    APP_DATABASE__CONNECTIONS__MAIN__DATABASE: ${{ runner.temp }}/ci.sqlite
  run: |
    php bin/console migrate
    php bin/console check
```

- The tests keep their memory database: only these two commands use the
  file.
- Use your connection's name in the variable (`MAIN` above), and add
  `pdo_sqlite` to the PHP extensions.
- Without the `migrate` line, `check` still reports the missing tables, so
  nothing is hidden.

skeleton-api ships this step; skeleton-web's workflow has it in a comment,
for when you add `mj-zana/database`.

## New in 2.22: every route attacked

`check` reads what each route declares
([security](security.md#new-in-221-every-route-says-who-may-reach-it)); this
test sends requests to see what each route does. A new route is attacked as
soon as it exists, without anyone writing a test for it:

```php
final class SecurityTest extends ApplicationTestCase
{
    protected function createApplicationBuilder(): ApplicationBuilder
    {
        return Bootstrap::builder(dirname(__DIR__))->withEnvironment('testing');
    }

    public function testEveryRouteWithstandsTheBaselineAttacks(): void
    {
        $this->console()->run('migrate');   // when the routes read the database
        $this->assertRoutesWithstandAttacks();
    }
}
```

Both skeletons ship it as `tests/SecurityTest.php`. The attacks, by route:

| The route | Attacked with | Fails when |
|---|---|---|
| Says it needs a user | an anonymous request, an expired session cookie | the request reaches the handler |
| Public, changes data, behind `CsrfMiddleware` | no token, a forged token, a cross-site `Origin` | the request reaches the handler |
| `#[RateLimit(max: n)]` | n + 1 requests | more than n reach the handler |
| Anyone may reach it | script, SQL, template, path traversal, NUL and long values; a list where a value is expected; a body that is not JSON; a 2 MB body | a server error, a PHP warning in your code, or an HTML page printing the script as sent |

- **"Says it needs a user"** is what the route declares: `#[RequiresAuthentication]`
  or `#[RequiresPermission]`, enforced or not, or your own sign-in middleware
  declared in `security.access_middleware`. This is where a declaration is
  tested against the truth: a sign-in middleware that does not refuse anyone
  fails here.
- **Nothing is written by the access attacks.** A probe, added as the
  route's last middleware, sees whether a request got through every check;
  for the access, CSRF and rate-limit attacks it answers itself, so the
  handler never runs. The hostile-input attacks run the handler, as a
  visitor could, so use the test database.
- **An ordinary request goes first.** When it already fails, for example
  because the route calls a service the test environment lacks, or a value
  in its path is unknown and answered with 500, the report says so once.
- **Parameters** get sample values that their patterns accept, including the
  values a pattern lists (`news|services`). A route whose pattern accepts
  none of them is reported as not attacked.
- **Leaving a route out** needs the reason, which the report shows:
  `$this->assertRoutesWithstandAttacks(except: ['POST /webhook' => 'Signed by the payment provider'])`.
  Name the route as the report does.
- Without mj-zana/security only the hostile-input attacks run.

### As a signed-in user

Only your application knows how its users sign in, so tell the test, and
which permissions that user holds:

```php
$token = $this->http()->post('/api/tokens', ['email' => 'grace@example.com', 'password' => '…'])->jsonPath('token');

$this->assertRoutesWithstandAttacks(
    signIn: static fn (ServerRequestInterface $request) => $request->withHeader('Authorization', "Bearer {$token}"),
    holding: [],   // grace has no role
);
```

For a website, sign in through its form and carry the session cookie:
`signIn: fn ($request) => $request->withHeader('Cookie', $cookie)->withCookieParams([$name => $value])`.

| The route | Attacked with, as that user | Fails when |
|---|---|---|
| Needs a permission the user does not hold | a signed-in request | it reaches the handler |
| Needs only a user, or permissions the user holds | a signed-in request | it does **not** get through: the sign-in or `holding` is wrong |
| The same, changing data behind `CsrfMiddleware` | no token, a forged token, a cross-site `Origin` | the request reaches the handler |
| The same | the hostile input above | a server error, a PHP warning in your code, or the script printed as sent |

- **Sign in the user with the fewest permissions**, ideally none: every route
  that needs another permission is then tried both ways. When even the
  smallest role holds some, as an editor in a back office does, name them in
  `holding`. A permission named there that the user does not hold is
  reported, so the list cannot drift from your roles.
- **The sign-in is checked.** When no signed-in request gets through to any
  route the user may reach, the test fails: attacks that never got in prove
  nothing.
- **CSRF needs a session.** With a bearer token, the CSRF attacks are skipped,
  and the report says so: a token is sent by your client, not by the browser
  another site controls.
- **Behind CSRF, signed-in hostile forms** are refused before the handler,
  so only GET requests are tried with hostile input there.
- **Not covered here:** one user reading another's data. That needs to know
  who owns what: see the next section.

## New in 2.27: one user's records stay theirs

Only your application knows who owns what, so tell the test: how to sign in
two users, and how to make a record of the first one's. Every route with a
parameter you name is then tried as both users, on a fresh record each time:

```php
public function testOrdersStayWithTheirOwners(): void
{
    $ada = $this->tokenFor('ada@example.com');
    $grace = $this->tokenFor('grace@example.com');

    $this->assertRecordsStayWithTheirOwners(
        owner: static fn (ServerRequestInterface $r) => $r->withHeader('Authorization', "Bearer {$ada}"),
        other: static fn (ServerRequestInterface $r) => $r->withHeader('Authorization', "Bearer {$grace}"),
        records: ['orderId' => fn (): string => $this->http()->withToken($ada)->post('/api/orders', [...])->jsonPath('data.id')],
        writes: true,
        json: true,
        bodies: ['PUT /api/orders/{orderId}' => ['note' => 'Leave at the door']],
    );
}
```

| Asked by | Must get | So that |
|---|---|---|
| The other user, first | 401, 403 or 404 | a page that shows the record, a change, or a redirect after one, fails |
| The owner, after | anything but 401, 403, 404 or a server error | the refusal is known to be about the owner, not a record that does not exist or a sign-in that does not work |

- **GET by default.** `writes: true` adds POST, PUT, PATCH and DELETE, each
  on a fresh record, with a valid CSRF token when mj-zana/security issues
  them. The other user asks first, so the owner's own delete cannot hide a
  leak.
- **Give a write a valid body** in `bodies`, by route. Many applications
  check a request's input before its owner: when both users get the same 400
  or 422, nothing is known, and the route is reported as not proven. A 400 or
  422 that only the other user gets fails: the answer depended on who asked.
- **An API** reads JSON: pass `json: true`, and the requests ask for JSON
  and send their bodies as JSON, with any CSRF token in `X-CSRF-Token`. A
  website gets form bodies.
- **Several parameters:** a record function may return the ids of several,
  `fn () => ['project' => $p, 'task' => $t]`. A route with a parameter that
  no record names is reported as not attacked.
- **Public routes are tried too**: a receipt page anyone may open can leak a
  record as well. One meant to be shared, for example by a secret link, is
  left out with the reason: `except: ['GET /receipts/{id}' => 'Shared by link']`.
- **The attacks must prove something.** When the owner gets through on no
  route, the test fails: check the owner's sign-in and the records made.
- **A parameter name means one thing.** When `userId` is a member on one
  route and any user on an admin route, leave the admin route out with its
  reason, or test it with a records function of its own.

Reference: the [testing README](https://github.com/mjnable/zana-testing).

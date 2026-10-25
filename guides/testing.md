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
  replaces `.env` when it exists.

The [website cookbook](../cookbook/website.md) tests a whole site this way.

Reference: the [testing README](https://github.com/mjnable/zana-testing).

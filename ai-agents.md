# Working with AI coding agents

Zana 2.7 helps AI coding agents (Claude Code, Codex, Copilot, Cursor and
others) work on a Zana application the way its developers do: they read the
rules of the application, look at what it contains, generate code with the
`make:` commands, and verify the result with the same checks. None of this
depends on an AI provider. The files and commands are plain text and JSON,
useful to people too.

Three things make it work:

| | What it gives the agent |
|---|---|
| `AGENTS.md` | The rules of the application and the workflow to follow, for the installed Zana version |
| `php bin/console inspect` | What the application contains: modules, services, routes, commands, configuration keys, packages |
| `php bin/console check` | Whether a change is right: wiring, architecture, routes, migrations, with a hint for each problem |

The documentation itself has an index for agents,
[`llms.txt`](llms.txt), with every page of this version and a line on each.

## AGENTS.md

New applications from the skeleton (2.7) have it. In an existing
application, with `mj-zana/maker` installed:

```
php bin/console make:agents
```

<!-- example: examples/guides/agents.php#plan -->
```php
// make:agents does this, with the version of the installed mj-zana/core.
$files = (new Generator($base))->planAgents('2.7');

foreach ($files as $file) {
    echo $file->relativePath, "\n";
}
```

<!-- example: examples/guides/agents.php#outline -->
```php
// AGENTS.md holds the rules; the other files point to it.
$agents = array_values(array_filter($files, static fn (GeneratedFile $f): bool => $f->relativePath === 'AGENTS.md'))[0];
foreach (explode("\n", $agents->contents) as $line) {
    if (str_starts_with($line, '#')) {
        echo $line, "\n";
    }
}
echo 'CLAUDE.md: ', trim($files[1]->contents), "\n";
```

<!-- example: examples/guides/agents.out -->
```text
AGENTS.md
CLAUDE.md
.github/copilot-instructions.md
# Instructions for AI coding agents
## How to work
## Architecture
## Services and configuration
## Web
## Do not
## Commands
CLAUDE.md: @AGENTS.md
```

- **`AGENTS.md`** holds everything: the workflow below, the module layers
  and what each may depend on, how to register services and read
  configuration, the web rules, what not to do, and the commands. It links
  to the documentation of the installed Zana version.
- **`CLAUDE.md`** imports it, and **`.github/copilot-instructions.md`**
  points to it, so each agent finds the same rules in the file it looks for.
- **Your own rules:** add a section of your own at the end of `AGENTS.md`.
- **After upgrading Zana:** run `php bin/console make:agents --force`, then
  add your section back. The skeleton's `tests/AgentsFileTest.php` fails
  until the file names the installed version.
- Existing files are not replaced without `--force`; `--zana-version=2.7`
  writes the file for another version.

## The workflow

`AGENTS.md` asks the agent to work in this order, the same order a careful
developer follows:

1. **Understand** the request and restate it.
2. **Inspect** the application: `php bin/console inspect --format=json`, or
   one section, e.g. `inspect routes`.
3. **Reuse** an existing feature, contract or service before adding one.
4. **Plan** the module and layers the change belongs to;
   `make:… --dry-run` shows what a generator would write.
5. **Generate** the structure with the `make:` commands.
6. **Write** the business logic in Domain and Application.
7. **Check:** `php bin/console check --format=json`, and fix every error.
8. **Test:** `composer test`.
9. **Summarise** the files changed, the decisions and the commands run.

## Checking a change

`check` runs every verification Zana knows and reports each problem as a
finding: an id, a severity, a message, the file when there is one, and a
hint on how to fix it. Here an agent added a route to a controller and
forgot to register the controller:

<!-- example: examples/guides/check.php#check -->
```php
$console = new ConsoleTester($app->container()->get(ConsoleKernel::class));
$result = $console->run('check --format=json');

$report = json_decode($result->output(), true);
echo "{$report['schema']}: {$report['status']} [exit {$result->exitCode()}]\n";
foreach ($report['findings'] as $finding) {
    echo "{$finding['severity']} {$finding['id']}: {$finding['message']}\n  hint: {$finding['hint']}\n";
}
```

<!-- example: examples/guides/check.out -->
```text
zana.check/1: error [exit 1]
error routing.handler: GET /shelves: its handler ShelfController is not registered in the container.
  hint: Register it in its module's register(): $services->transient(\ShelfController::class);
warning shop.opening_hours: The opening hours are not set.
  hint: Set shop.opening_hours in config/shop.php.
```

The second finding comes from the application's own check; see
[the console guide](guides/console.md#inspect-and-check-27) for the checks
`check` runs and how to add one.

## Rules worth knowing

- **Nothing here calls an AI.** Zana has no dependency on an AI provider;
  the agent runs on your machine or your provider's, and uses the console
  like a developer.
- **No secrets reach the agent through Zana.** `inspect` shows
  configuration keys and their types, never values.
- **Review what an agent writes** as you would review a colleague's work.
  `check` and the tests catch wiring and architecture mistakes, not wrong
  business rules.
- **The JSON formats are versioned** (`zana.inspect/1`, `zana.check/1`):
  fields are only added within a version.

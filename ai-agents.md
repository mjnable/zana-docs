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
   `make:… --dry-run --format=json` lists the files a generator would write
   and the lines it would register.
5. **Generate** the structure with the `make:` commands.
6. **Write** the business logic in Domain and Application.
7. **Check:** `php bin/console check --format=json`, and fix every error.
8. **Test:** `composer test`.
9. **Summarise** the files changed, the decisions and the commands run.

Nothing in it is specific to one agent: it is plain commands and files, and
a developer without an agent follows the same steps.

## A walkthrough: a library API (2.8)

The request: *"Let people add books, with a title and an author, and list
them, through a JSON API."* This walkthrough follows the workflow in an
application with HTTP, routing, the mediator, serialization, validation, the
database and the console, as in
[`examples/agents/library`](examples/agents/library). Its test suite
rebuilds that application from the commands on this page, so they are
exactly what produced it.

**1–3. Understand, inspect, reuse.** `inspect` shows no module with books,
so the feature gets its own module, `Library`.

**4–5. Plan and generate.** One module, its entity with a value object for
the title and a database repository, and two use cases exposed over HTTP:

```shell
php bin/console make:module Library
php bin/console make:entity Library Book
php bin/console make:value-object Library BookTitle
php bin/console make:repository Library Book
php bin/console make:feature Library AddBook --field=title:string --field=author:string --http
php bin/console make:feature Library ListBooks --query --http
```

Add `--dry-run --format=json` to any of them to see its plan first.
`make:feature … --http` writes a vertical slice:

| File | |
|---|---|
| `Application/Features/AddBook/AddBookCommand.php` | The command: `title` and `author`, typed; a required text must not be blank |
| `Application/Features/AddBook/AddBookHandler.php` | Its handler, empty |
| `Presentation/Api/AddBookAction.php` | `POST /api/library/add-book`: reads the JSON, sends the command, answers 204 |
| `Presentation/Api/ApiInput.php` | Once per module: JSON or query string → a validated command or query; 400 or 422 with the field errors |
| `Tests/Presentation/AddBookApiTest.php` | The route answers; missing fields are refused |
| `Tests/Application/AddBookHandlerTest.php` | Marked incomplete until the handler does something |

and registers the handler, the action and its route in `LibraryModule`.
`make:repository` writes `DatabaseBookRepository`, which stores a book with
an optimistic version check, and the migration of its table. At this point
`php bin/console migrate` and `php bin/console check` already pass.

**6. Write the business logic.** The generated code is the structure; the
rules are yours. A book gets its title and author:

<!-- example: examples/agents/library/modules/Library/Domain/Book.php#entity -->
```php
private function __construct(
    BookId $id,
    public private(set) BookTitle $title,
    public private(set) string $author,
) {
    parent::__construct($id);
}

public static function create(BookTitle $title, string $author): self
{
    return new self(BookId::generate(), $title, $author);
}

/**
 * Rebuild a stored Book (used by its repository); records no event.
 */
public static function restore(BookId $id, int $version, BookTitle $title, string $author): self
{
    $entity = new self($id, $title, $author);
    $entity->version = $version;

    return $entity;
}
```

The repository stores them (and the migration gets the two columns):

<!-- example: examples/agents/library/modules/Library/Infrastructure/Persistence/DatabaseBookRepository.php#columns -->
```php
/**
 * The stored columns, besides id and version.
 *
 * @return array<string, mixed>
 */
private static function row(Book $entity): array
{
    return ['title' => $entity->title->value, 'author' => $entity->author];
}

/**
 * @param array<string, mixed> $row
 */
private static function hydrate(array $row): Book
{
    $text = static fn (string $key): string => is_scalar($row[$key] ?? null) ? (string) $row[$key] : '';

    return Book::restore(BookId::fromString($text('id')), (int) $text('version'), new BookTitle($text('title')), $text('author'));
}
```

The handler creates the book; `BookTitle` trims the title and refuses an
empty one:

<!-- example: examples/agents/library/modules/Library/Application/Features/AddBook/AddBookHandler.php#handler -->
```php
#[Handles(AddBookCommand::class)]
final readonly class AddBookHandler
{
    public function __construct(private BookRepositoryInterface $books)
    {
    }

    public function __invoke(AddBookCommand $command): void
    {
        $this->books->add(Book::create(new BookTitle($command->title), $command->author));
    }
}
```

`ListBooksHandler` returns the books by title through a new `all()` on the
repository interface, and the handler tests now assert what the handlers
do, with an in-memory repository. The API tests store books, so they
migrate a fresh database (SQLite in memory, `config/testing/database.php`):

<!-- example: examples/agents/library/modules/Library/Tests/Presentation/AddBookApiTest.php#migrate -->
```php
protected function setUp(): void
{
    parent::setUp();
    $this->assertSame(0, $this->console()->run('migrate')->exitCode(), 'a fresh database (config/testing)');
}
```

These are the files written by hand after generating:
`Domain/Book.php`, `Domain/BookRepositoryInterface.php`,
`Infrastructure/Persistence/DatabaseBookRepository.php`, the migration,
`Application/Features/AddBook/AddBookHandler.php`,
`Application/Features/ListBooks/ListBooksHandler.php`, the four tests in
`Tests/Application` and `Tests/Presentation`, and the new
`Tests/InMemoryBookRepository.php`. Every other file is as generated.

**7–8. Check and test.** `php bin/console check` passes: the container
builds, the architecture rules hold, every route reaches a registered
handler, and the migrations are applied. `composer test` runs the eight
tests of the module.

**9. Summarise.** The agent reports the module and the files it created,
the two endpoints, the decision to keep the title as a value object, and
the results of `check` and the tests.

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

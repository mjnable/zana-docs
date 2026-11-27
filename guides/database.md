# Database

`mj-zana/database` provides PDO connections, a query builder, a schema
builder, module-aware migrations and a transactional unit of work. It
supports SQLite, MySQL/MariaDB and PostgreSQL.

```
composer require mj-zana/database
```

## Schema

<!-- example: examples/guides/database.php#schema -->
```php
$db->schema()->create('patients', static function (Blueprint $t): void {
    $t->id();
    $t->string('name', 100);
    $t->date('born_on');
    $t->index('name');
});

// Later, in another migration: change the existing table.
$db->schema()->table('patients', static function (Blueprint $t): void {
    $t->string('city', 60)->nullable();
    $t->boolean('active')->default(true);
});
```

## Queries

<!-- example: examples/guides/database.php#queries -->
```php
$db->table('patients')->insert([
    ['name' => 'Ada Lovelace', 'born_on' => '1815-12-10', 'city' => 'London'],
    ['name' => 'Grace Hopper', 'born_on' => '1906-12-09', 'city' => 'New York'],
    ['name' => 'Alan Turing', 'born_on' => '1912-06-23', 'city' => 'London'],
]);

$londoners = $db->table('patients')
    ->select('name', 'born_on')
    ->where('city', 'London')
    ->where('born_on', '<', '1900-01-01')
    ->orderBy('name')
    ->get();
echo json_encode($londoners), "\n";

echo $db->table('patients')->where('active', true)->count(), " active\n";
echo $db->table('patients')->where('name', 'like', 'A%')->value('city'), "\n";
```

## Transactions and safety nets

<!-- example: examples/guides/database.php#transactions -->
```php
try {
    $db->transaction(static function (ConnectionInterface $db): void {
        $db->table('patients')->where('name', 'Alan Turing')->update(['city' => 'Manchester']);
        throw new RuntimeException('something went wrong');
    });
} catch (RuntimeException) {
}
echo $db->table('patients')->where('name', 'Alan Turing')->value('city'), " (rolled back)\n";

try {
    $db->table('patients')->delete();                  // no where clause
} catch (DatabaseException $e) {
    echo $e->getMessage(), "\n";
}
```

The whole example prints:

<!-- example: examples/guides/database.out -->
```text
[{"name":"Ada Lovelace","born_on":"1815-12-10"}]
3 active
London
London (rolled back)
Refusing to delete every row of patients without a where clause; pass force: true to do it on purpose.
```

## In an application

Register `DatabaseServiceProvider` (with `ConsoleServiceProvider` for the
commands), and configure `config/database.php`:

```php
return [
    'default' => 'sqlite',
    'connections' => [
        'sqlite' => ['driver' => 'sqlite', 'database' => 'var/app.sqlite'],   // relative to the base path
        'mysql' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'database' => 'app', 'username' => 'app', 'password' => ''],
    ],
];
```

- **Connections:** inject `ConnectionInterface` for the default connection.
  `DatabaseManager::connection('name')` gives any other.
- **A SQLite file** is made on first use, and since 2.27.1 its folder too
  (`var/` above).
- **Migrations:** they live in `database/migrations` and in each module's
  `Infrastructure/Persistence/Migrations`, and run on Doctrine Migrations
  since 2.37. See [Migrations](#migrations) below.

  ```
  php bin/zana make:migration create_clinics_table [--path=modules/Clinics/Infrastructure/Persistence/Migrations]
  php bin/zana make:migration add_launch_dates --data
  php bin/zana migrate
  php bin/zana migrate:status
  php bin/zana migrate:rollback --step=1
  ```
- **Unit of work:** `UnitOfWorkInterface` (from [ddd](ddd.md)) resolves, per
  scope, to a transactional unit of work on the default connection:

  ```php
  $order = $this->unitOfWork->transactional(function () use ($command): Order {
      $order = Order::place(...);
      $this->orders->add($order);

      return $order;
  });
  ```

## Migrations

Since 2.37, migrations run on [Doctrine Migrations](https://www.doctrine-project.org/projects/migrations.html),
as in Symfony, so a migration can change data as well as the schema. Zana keeps its own commands, its
`check` and `inspect` findings, and its record of what ran (`zana_migrations`).

Two kinds of migration live side by side, in the same directories:

- **Zana migration files** (`2026_11_01_120000_create_case_studies.php`),
  written before 2.37. They keep running as they are, and what ran is still
  known: nothing needs converting.
- **Doctrine migration classes** (`Version20261124120000.php`), which
  `make:migration` writes since 2.37, and [maker](maker.md)'s
  `make:repository`, `make:crud` and `make:plan` since 2.38. A class extends
  `Doctrine\Migrations\AbstractMigration`. Its namespace follows your
  `composer.json` autoload, or the directory's path.

They run together, in the order of the date in their name.

<!-- example: examples/guides/migrations/earlier/2026_11_01_120000_create_case_studies.php -->
```php
<?php

declare(strict_types=1);

use Zana\Database\Migration\Migration;
use Zana\Database\Schema\Blueprint;
use Zana\Database\Schema\Schema;

// A migration written before 2.37: it keeps running as it is.
return new class () extends Migration {
    public function up(Schema $schema): void
    {
        $schema->create('case_studies', static function (Blueprint $t): void {
            $t->id();
            $t->string('slug', 100);
            $t->text('summary');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('case_studies');
    }
};
```

### Schema migrations

`make:migration add_launched_on` writes a schema migration. It changes the
schema through Doctrine DBAL's schema objects, which give the same result on
SQLite, MySQL and PostgreSQL, or with `$this->addSql()` for what they cannot
express:

<!-- example: examples/guides/migrations/release/Version20261124110000.php -->
```php
<?php

declare(strict_types=1);

namespace Docs\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * A schema migration, as make:migration add_launched_on writes it, filled in.
 */
final class Version20261124110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'add_launched_on';
    }

    // A schema change commits the transaction on MySQL and MariaDB anyway.
    public function isTransactional(): bool
    {
        return !$this->platform instanceof AbstractMySQLPlatform;
    }

    public function up(Schema $schema): void
    {
        $schema->getTable('case_studies')->addColumn('launched_on', Types::DATE_MUTABLE, ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $schema->getTable('case_studies')->dropColumn('launched_on');
    }
}
```

A schema migration runs in a transaction on SQLite and PostgreSQL, where a
schema change can be undone. On MySQL and MariaDB it runs without one,
because a schema change commits the transaction there anyway. Change no data
in it: on those databases, data changed alongside could not be undone.

DBAL 4.5 deprecates `createTable()`, `addColumn()` and the other methods
that change a schema in place. It offers editors instead, but Doctrine
Migrations cannot pass those to `up()` yet. The methods work, and say they
are deprecated only when Doctrine's deprecations are switched on. The
generated migration will change when Doctrine Migrations supports the
replacement.

### Data migrations

A data migration ships a change to rows with the release that needs it, so
it runs once on every database, in order with the schema changes, through
the `migrate` each deployment runs. Typical cases: correcting content
already in production, filling a new column from others, renaming a stored
code, or removing rows a bug created. `make:migration add_launch_dates
--data` writes one:

<!-- example: examples/guides/migrations/release/Version20261124120000.php -->
```php
<?php

declare(strict_types=1);

namespace Docs\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * A data migration, as make:migration add_launch_dates --data writes it, filled in. It runs in a transaction on
 * every database, and has no down(): it cannot be rolled back.
 */
final class Version20261124120000 extends AbstractMigration
{
    // Copied in: the migration must give the same result after the code changes.
    private const string OLD = 'The site went live in November.';
    private const string NEW = 'The site first went live in July 2024, and its new version in November 2026.';

    public function getDescription(): string
    {
        return 'add_launch_dates';
    }

    public function up(Schema $schema): void
    {
        // Read with $this->connection: it runs at once.
        $found = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM case_studies WHERE summary = ?', [self::OLD]);
        $this->write("{$found} case study to change.");
        $this->warnIf($found === 0, 'No case study has the old summary: nothing to change.');

        // Write with addSql(): queued until up() returns, so after every read. Matching the old value leaves a
        // summary an editor has changed alone, and makes a second run change nothing.
        $this->addSql('UPDATE case_studies SET summary = ? WHERE summary = ?', [self::NEW, self::OLD]);
    }
}
```

A data migration runs in a transaction on every database, with its line in
`zana_migrations`, so it changes everything or nothing. It has no `down()`,
so it cannot be rolled back. To write one:

- **Change no schema in it.** On MySQL and MariaDB a schema change commits
  the transaction, and the error then says that the statements before it
  stay applied.
- **Match the old value**, so a database already changed by hand is left
  alone and a second run changes nothing.
- **Pass values as bindings,** never in the SQL.
- **Read with `$this->connection`, write with `$this->addSql()`.** A query
  through `$this->connection` runs at once, while `addSql()` is queued until
  `up()` returns. So a read never sees what the same migration's `addSql()`
  writes, and `$this->connection->insert()` into a table that `addSql()`
  creates fails, because the table does not exist yet.
- **Use tables and values copied in,** never repositories, entities or
  application services. They follow today's code, while the migration must
  still work on the schema of its own time.
- **Say what it did:** `$this->write()` and `$this->warnIf()` are shown by
  `migrate`, and sent to the application's log.
- **Correct the seed too.** A correction of content that a seed command
  writes goes in the seed as well, so a new database has it. The migration
  then finds nothing to change there.

Run in two releases of one application, the migrations above print:

<!-- example: examples/guides/database-migrations.php#run -->
```php
// The earlier release: a Zana migration file, then content entered by editors.
(new Migrator($db, new MigrationRepository($db), [$earlier]))->migrate();
$db->table('case_studies')->insert([
    ['slug' => 'minscom', 'summary' => 'The site went live in November.'],
    ['slug' => 'gnsdrc', 'summary' => 'Rewritten by an editor.'],
]);

// This release adds a schema migration and a data migration: migrate runs both, after what ran before.
$migrator = new Migrator($db, new MigrationRepository($db), [$earlier, $release]);
foreach ($migrator->migrate() as $name) {
    echo "Ran {$name}\n";
}
foreach ($migrator->messages() as $message) {          // what `migrate` shows
    echo "  {$message['level']}: {$message['message']}\n";
}
foreach ($db->table('case_studies')->orderBy('slug')->get() as $row) {
    echo "{$row['slug']}: {$row['summary']}\n";
}

echo 'Irreversible: ', implode(', ', $migrator->irreversible()), "\n";
try {
    $migrator->rollback();
} catch (MigrationException $e) {
    echo $e->getMessage(), "\n";
}
```

<!-- example: examples/guides/database-migrations.out -->
```text
Ran Docs\Migrations\Version20261124110000
Ran Docs\Migrations\Version20261124120000
  notice: 1 case study to change.
gnsdrc: Rewritten by an editor.
minscom: The site first went live in July 2024, and its new version in November 2026.
Irreversible: Docs\Migrations\Version20261124120000
Cannot roll back Docs\Migrations\Version20261124120000: it has no down(), so it cannot be undone. Nothing was rolled back.
```

When not to use one: content an editor will change again belongs in the
back office, recurring work in the [scheduler](scheduler.md), and a backfill
of many rows in a command that works in batches and can resume.

### Content history is not migration history

`zana_migrations` records which migrations ran. Content that an application
keeps revisions of, with [audit](audit.md)'s `Revisions`, has a history of its
own, in `audit_revisions`. A data migration that changes such content records
the change there too, as an edit in the back office would:

- the text before, unless it is already the latest revision;
- the text after, with a note naming the migration.

It writes the rows itself, in the table's format (the whole translation as
JSON, the next `version`, `created_at` as a Unix time), and does not call
the `Revisions` service. Then the change can be restored from the history.

```php
$latest = $this->connection->fetchAssociative(
    'SELECT version, data FROM audit_revisions WHERE type = ? AND record_id = ? AND locale = ? ORDER BY version DESC LIMIT 1',
    ['case-studies', $id, $locale],
);
$version = $latest === false ? 0 : (int) $latest['version'];
if ($latest === false || json_decode((string) $latest['data'], true) !== $before) {
    $this->addSql($insert, ['case-studies', $id, $locale, ++$version, json_encode($before, JSON_UNESCAPED_UNICODE), null, time(), null]);
}
$this->addSql($insert, ['case-studies', $id, $locale, ++$version, json_encode($after, JSON_UNESCAPED_UNICODE), null, time(), 'Launch dates added (migration Version20261124120000)']);
```

### Rolling back

`migrate:rollback` reverts the last batch, newest migration first. A
migration with no `down()` cannot be undone. A batch that holds one is
refused before any `down()` runs, and nothing changes:

```text
Cannot roll back Docs\Migrations\Version20261124120000: it has no down(), so it cannot be undone. Nothing was rolled back.
```

`migrate:status` marks such a migration as irreversible. To undo a data
change, restore the earlier values from the content history, or write
another migration.

### Rules for migrations

- **Never rename a Doctrine migration class or change its namespace once it
  has run.** It is recorded by its full class name. Renamed or moved, it
  becomes a new, pending migration that runs again, and its old line names
  a migration whose file is gone (`check` reports it).
- **Run one `migrate` at a time.** Two runs on one database at the same
  moment are not serialized. Run migrations from a single deployment step,
  never from each web server or worker as it starts.
- **Doctrine's own commands are not available.** `migrations:migrate`, its
  dry runs and `--write-sql` are not registered: use `migrate`,
  `migrate:rollback`, `migrate:status`, `check` and `inspect`. A dry run
  would really run a Zana migration file, whose schema builder executes each
  statement at once.
- **MySQL tables are InnoDB with utf8mb4,** whether a Zana file or a
  Doctrine migration's schema objects create them (2.37.1). A table created
  with `addSql()` takes what its SQL says.

### Migrations that packages write

`queue:table`, `audit:install-log`, `outbox:install` and
`idempotency:install` write Zana migration files, the one exception to
"new migrations are Doctrine classes". Each file has one line,
`return new \Zana\Queue\QueueTablesMigration();`, so the package keeps its
tables' schema in one place, its own class, and an application gets a change
to it with the package. Running one again writes nothing it wrote before.

maker's generators follow the rule. `make:repository`, `make:crud` and
`make:plan` find a migration they wrote before, of either kind, and keep it,
so running them again never writes a second migration for a table.

## Rules worth knowing

- **Values are always bound.** Errors carry the SQL but never the values.
- **No accidental mass changes:** `update()` and `delete()` without a
  `where` throw, unless you pass `force: true`.
- **Nested transactions:** they use savepoints. Only the outermost one
  commits.
- **Changing tables:** `Schema::table()` adds columns and indexes and drops
  columns and indexes.
  - New columns must be nullable or have a default, because existing rows
    need a value.
  - Primary keys and foreign keys cannot be added to an existing table.
- **MySQL tables are always InnoDB** with utf8mb4, even where the server
  defaults to MyISAM (as WAMP does). MyISAM has no transactions.
- **Modules do not share tables.** Store another module's ids without a
  foreign key, and look the records up through its public contract (see
  the [cookbook](../cookbook/modules.md)).

## New in 2.12: on Doctrine DBAL, with counters, upserts and locks

The connection now runs on [Doctrine DBAL](https://www.doctrine-project.org/projects/dbal.html).
Nothing changes in your code: the query and schema builders keep their API
and their safety nets, and DBAL compiles them for SQLite, MySQL and
PostgreSQL. Four additions cover what inventory, payments and imports need:

<!-- example: examples/guides/database-counters.php#reserve -->
```php
// Reserve seats only while sold + seats <= capacity: one statement, so two buyers can never both get
// the last seat. The affected row count says whether it worked.
$reserve = static fn (int $seats): bool => $db->table('ticket_types')
    ->where('name', 'VIP')
    ->whereColumn('sold', '<=', 'capacity', -$seats)
    ->increment('sold', $seats) === 1;

echo 'Reserve 3: ', $reserve(3) ? 'yes' : 'no', "\n";
echo 'Reserve 3: ', $reserve(3) ? 'yes' : 'no', "\n";   // only 2 seats left
echo 'Reserve 2: ', $reserve(2) ? 'yes' : 'no', "\n";
```

<!-- example: examples/guides/database-counters.php#upsert -->
```php
// Insert, or update the row with the same unique name.
$db->table('ticket_types')->upsert([
    ['name' => 'VIP', 'sold' => 0, 'capacity' => 8],
    ['name' => 'Standard', 'sold' => 0, 'capacity' => 200],
], uniqueBy: 'name', update: ['capacity']);
```

<!-- example: examples/guides/database-counters.php#lock -->
```php
// Read a row and keep others from changing it until the transaction ends (FOR UPDATE; SQLite
// serializes write transactions instead).
$vip = $db->transaction(static fn (ConnectionInterface $tx): ?array => $tx->table('ticket_types')->where('name', 'VIP')->lockForUpdate()->first());
```

<!-- example: examples/guides/database-counters.out -->
```text
Reserve 3: yes
Reserve 3: no
Reserve 2: yes
Standard: 0 of 200 sold
VIP: 5 of 8 sold
Locked: VIP
```

- **`increment()` and `decrement()`** change a column relative to itself,
  optionally with other columns; like `update()`, they need a `where`.
- **`whereColumn($first, $operator, $second, $offset)`** compares two
  columns of the row; `-3` reads "capacity - 3".
- **`upsert($rows, $uniqueBy, $update)`** inserts, or updates the
  conflicting rows' `$update` columns (all others by default, none with
  `[]`). On MySQL any unique key of the table detects the conflict.
- **`lockForUpdate()`** adds `FOR UPDATE`; SQLite has no row locks, and
  serializes write transactions instead.
- **Retry on deadlocks:** `DatabaseException::isRetryable()` is true for a
  deadlock or a lock wait timeout; run the transaction again.
- **MySQL counts changed rows:** an update that writes the values a row
  already has returns 0, unlike SQLite and PostgreSQL.
- `DbalConnection::dbal()` gives the DBAL connection to integrations that
  must share its transactions. `pdo()` and the PDO `Connection` are
  deprecated.

## New in 2.20: request input, grouped conditions, constraints and TLS

### Values and columns that come from a request

```php
// A search box: the user's "%" and "_" are plain characters, on every database.
$db->table('accounts')->whereLike('email', $request->query('q') ?? '', LikeMatch::StartsWith)->get();

// A sort and a field list from the query string: only the names you list count.
$db->table('events')
    ->selectAllowed($request->query('fields'), ['title', 'starts_at', 'town' => 'city'])
    ->orderByAllowed($request->query('sort'), ['title', 'newest' => 'created_at'], default: 'newest', direction: $request->query('dir'))
    ->get();

// A form: name the fields a visitor may set. insert($input->all()) would let them add is_admin=1.
$db->table('contacts')->insert(FormInput::from($request)->only('name', 'email'));
```

- **`whereLike($column, $text, LikeMatch::StartsWith|Contains|EndsWith)`**
  and `orWhereLike()` escape `%`, `_` and `!`, and add `ESCAPE '!'`. With
  `where('email', 'like', $input . '%')`, a user's `%` matches anything, and
  escaping by hand differs per database. Case follows the database: SQLite
  and MySQL's default collation ignore it, PostgreSQL does not.
- **`orderByAllowed()` and `selectAllowed()`:** `orderBy()` and `select()`
  take any real column, so `?sort=reset_token` would sort by a secret. A
  map (`'newest' => 'created_at'`) keeps column names out of URLs. An
  unknown name, an array or a bad direction falls back to the default and
  never causes an error. A default that is not in the list throws: it is a
  mistake in code.
- **Values must be scalars.** A form field sent as `email[]=x` is an array,
  and an array was stored as the text `Array`. Binding an array or an object
  that is not `Stringable` now throws a `DatabaseException`, which names the
  type, never the value. A `Stringable` binds as its string.

### Grouped conditions

```php
// city = 'London' AND (age < 18 OR age > 65)
$db->table('patients')
    ->where('city', 'London')
    ->where(fn (QueryBuilder $q) => $q->where('age', '<', 18)->orWhere('age', '>', 65))
    ->get();
```

`where(Closure)` and `orWhere(Closure)` put the group's conditions in
parentheses. Groups nest, and an empty group adds nothing.

### Constraints and column types

```php
$schema->create('stock', static function (Blueprint $t): void {
    $t->id();
    $t->integer('sold')->default(0);
    $t->integer('quantity');
    $t->smallInteger('rating')->nullable();
    $t->check('sold_within_quantity', 'sold <= quantity');
    $t->check('rating_range', 'rating BETWEEN 1 AND 5');
});
```

- **`check($name, $expression)`** declares a CHECK constraint, so the
  database refuses a row that breaks the invariant, whatever the code
  does. It is named `{table}_{name}_check`, because MySQL needs constraint
  names unique across the database. The expression is SQL you write, never
  user input.
- **In `table()`**, `check()` works on PostgreSQL and MySQL. SQLite cannot
  add a constraint to an existing table, so declare it in `create()`.
- **SQLite rebuilds a table** to add an index or drop a column, and the
  rebuilt table would lose its CHECK constraints and triggers. `table()`
  now refuses such a change, with an explanation. Adding a nullable column
  needs no rebuild and still works. To make the change anyway, recreate the
  table in the migration, or write it in raw SQL.
- **`smallInteger()`** is `SMALLINT` on PostgreSQL and MySQL, and `INTEGER`
  on SQLite.
- **`uuid()` holds canonical UUIDs.** Store `Uuid::v7()`, not
  `bin2hex(random_bytes(16))`. PostgreSQL accepts 32 hexadecimal digits but
  returns them hyphenated, so such an id is not found by its own value
  there, while every SQLite test passes. Put random hex ids in
  `string(32)`.

### Encrypted connections

```php
// config/database.php, for a database on another host
'main' => [
    'driver' => 'pgsql', 'host' => 'db.internal', 'database' => 'app', 'username' => 'app', 'password' => '…',
    'sslmode' => 'verify-full', 'sslrootcert' => '/etc/ssl/db-ca.pem',
],
'reports' => [
    'driver' => 'mysql', 'host' => 'mysql.internal', 'database' => 'reports', 'username' => 'app', 'password' => '…',
    'ssl_ca' => '/etc/ssl/mysql-ca.pem', 'ssl_verify' => true,
],
```

- **PostgreSQL:** `sslmode` (`disable`, `allow`, `prefer`, `require`,
  `verify-ca`, `verify-full`; anything else is refused), `sslrootcert`,
  `sslcert`, `sslkey` and `sslcrl`. Paths may be Windows paths and may
  contain spaces. Setting `PGSSLMODE` in `.env` is not enough: PHP's
  `.env` loading never reaches libpq on Windows.
- **MySQL:** `ssl_ca`, `ssl_cert`, `ssl_key` and `ssl_verify`.
- **`check` reports `database.tls`** when a connection to another host is
  not encrypted, as the server itself reports it (`pg_stat_ssl`,
  `Ssl_cipher`): an error in production, a warning elsewhere. Connections
  to `localhost`, `127.0.0.1` and `::1` are not reported.

### Smaller changes

- **`scalar()` keeps `false`.** It returns `null` only for no row or SQL
  `NULL`. Before, a PostgreSQL `false` read as `null`. SQLite and MySQL
  return `0` and `1`, as before.
- **SQLite quotes names with backticks.** It read a double-quoted name that
  matched no column as a string, so `where('nope', 'nope')` matched every
  row. An unknown column now fails, as on the other databases.
- **Static analysis:** the methods that read or write the database are
  marked `@phpstan-impure`, so PHPStan no longer calls the re-check in a
  race-safe insert (check, insert, check again on `DatabaseException`)
  "always false".

Reference: the [database README](https://github.com/mjnable/zana-database).

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
- **Migrations:** they live in `database/migrations` and in each module's
  `Infrastructure/Persistence/Migrations`. Each file returns a
  `Migration`:

  ```php
  return new class () extends Migration {
      public function up(Schema $schema): void { $schema->create('clinics', fn (Blueprint $t) => $t->id()); }
      public function down(Schema $schema): void { $schema->drop('clinics'); }
  };
  ```

  ```
  php bin/zana make:migration create_clinics_table [--path=modules/Clinics/Infrastructure/Persistence/Migrations]
  php bin/zana migrate
  php bin/zana migrate:status
  php bin/zana migrate:rollback --step=1
  ```

  Migrations run in file-name order across all paths. A batch runs in one
  transaction on SQLite and PostgreSQL; MySQL cannot roll back DDL.
- **Unit of work:** `UnitOfWorkInterface` (from [ddd](ddd.md)) resolves, per
  scope, to a transactional unit of work on the default connection:

  ```php
  $order = $this->unitOfWork->transactional(function () use ($command): Order {
      $order = Order::place(...);
      $this->orders->add($order);

      return $order;
  });
  ```

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

Reference: the [database README](https://github.com/mjnable/zana-database).

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

Reference: the [database README](https://github.com/mjnable/zana-database).

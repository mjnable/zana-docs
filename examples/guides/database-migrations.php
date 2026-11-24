<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Database\ConnectionFactory;
use Zana\Database\Migration\MigrationException;
use Zana\Database\Migration\MigrationRepository;
use Zana\Database\Migration\Migrator;

// In an application, DatabaseServiceProvider makes the migrator, with database/migrations and each module's
// Infrastructure/Persistence/Migrations, and `php bin/console migrate` runs it. Here, two releases of one
// application, on an in-memory SQLite database:
$db = ConnectionFactory::create(['driver' => 'sqlite', 'database' => ':memory:']);
$earlier = __DIR__ . '/migrations/earlier';
$release = __DIR__ . '/migrations/release';

// region: run
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
// endregion: run

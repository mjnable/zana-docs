<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Database\ConnectionFactory;
use Zana\Database\ConnectionInterface;
use Zana\Database\DatabaseException;
use Zana\Database\Schema\Blueprint;

// In an application, DatabaseServiceProvider builds connections from config/database.php,
// and ConnectionInterface is injected. Here, an in-memory SQLite database:
$db = ConnectionFactory::create(['driver' => 'sqlite', 'database' => ':memory:']);

// region: schema
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
// endregion: schema

// region: queries
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
// endregion: queries

// region: transactions
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
// endregion: transactions

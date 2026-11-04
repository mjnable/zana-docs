<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Database\ConnectionFactory;
use Zana\Database\ConnectionInterface;
use Zana\Database\Schema\Blueprint;

$db = ConnectionFactory::create(['driver' => 'sqlite', 'database' => ':memory:']);
$db->schema()->create('ticket_types', static function (Blueprint $t): void {
    $t->id();
    $t->string('name', 60)->unique();
    $t->integer('sold')->default(0);
    $t->integer('capacity');
});
$db->table('ticket_types')->insert(['name' => 'VIP', 'capacity' => 5]);

// region: reserve
// Reserve seats only while sold + seats <= capacity: one statement, so two buyers can never both get
// the last seat. The affected row count says whether it worked.
$reserve = static fn (int $seats): bool => $db->table('ticket_types')
    ->where('name', 'VIP')
    ->whereColumn('sold', '<=', 'capacity', -$seats)
    ->increment('sold', $seats) === 1;

echo 'Reserve 3: ', $reserve(3) ? 'yes' : 'no', "\n";
echo 'Reserve 3: ', $reserve(3) ? 'yes' : 'no', "\n";   // only 2 seats left
echo 'Reserve 2: ', $reserve(2) ? 'yes' : 'no', "\n";
// endregion: reserve

// region: upsert
// Insert, or update the row with the same unique name.
$db->table('ticket_types')->upsert([
    ['name' => 'VIP', 'sold' => 0, 'capacity' => 8],
    ['name' => 'Standard', 'sold' => 0, 'capacity' => 200],
], uniqueBy: 'name', update: ['capacity']);
// endregion: upsert

// region: lock
// Read a row and keep others from changing it until the transaction ends (FOR UPDATE; SQLite
// serializes write transactions instead).
$vip = $db->transaction(static fn (ConnectionInterface $tx): ?array => $tx->table('ticket_types')->where('name', 'VIP')->lockForUpdate()->first());
// endregion: lock

foreach ($db->table('ticket_types')->orderBy('name')->get() as $row) {
    echo "{$row['name']}: {$row['sold']} of {$row['capacity']} sold\n";
}
echo 'Locked: ', $vip['name'] ?? '-', "\n";

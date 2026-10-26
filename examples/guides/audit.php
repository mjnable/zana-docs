<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Audit\InMemoryRevisionStore;
use Zana\Audit\Revisions;

$now = strtotime('2026-10-26 09:00 UTC');
$author = 'ada';

// region: revisions
// AuditServiceProvider stores revisions in the database, with the signed-in user as author.
$revisions = new Revisions(new InMemoryRevisionStore(), static function () use (&$author): string {
    return $author;
}, static function () use (&$now): int {
    return $now;
});

$revisions->record('post', '42', 'fr', ['title' => 'Lancement', 'body' => 'Bientôt.']);
[$author, $now] = ['bob', $now + 3600];
$revisions->record('post', '42', 'fr', ['title' => 'Lancement', 'body' => 'Aujourd’hui !']);

foreach ($revisions->history('post', '42', 'fr') as $revision) {
    echo "v{$revision->version} by {$revision->author} at ", gmdate('H:i', $revision->createdAt), "\n";
}

[$latest, $previous] = $revisions->history('post', '42', 'fr');
echo 'Changed: ', json_encode($latest->diff($previous), JSON_UNESCAPED_UNICODE), "\n";

$data = $revisions->restore('post', '42', 'fr', 1);   // save $data to the post yourself
echo 'Restored: ', $data['body'], ' (now v', $revisions->latest('post', '42', 'fr')?->version, ")\n";
// endregion: revisions

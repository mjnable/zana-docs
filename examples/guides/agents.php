<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Maker\GeneratedFile;
use Zana\Maker\Generator;

$base = sys_get_temp_dir() . '/zana-docs-agents-' . bin2hex(random_bytes(4));
mkdir($base);

// region: plan
// make:agents does this, with the version of the installed mj-zana/core.
$files = (new Generator($base))->planAgents('2.7');

foreach ($files as $file) {
    echo $file->relativePath, "\n";
}
// endregion: plan

// region: outline
// AGENTS.md holds the rules; the other files point to it.
$agents = array_values(array_filter($files, static fn (GeneratedFile $f): bool => $f->relativePath === 'AGENTS.md'))[0];
foreach (explode("\n", $agents->contents) as $line) {
    if (str_starts_with($line, '#')) {
        echo $line, "\n";
    }
}
echo 'CLAUDE.md: ', trim($files[1]->contents), "\n";
// endregion: outline

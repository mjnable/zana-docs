<?php

declare(strict_types=1);

// Rewrites every embedded code block from its example source. Run after
// changing an example; `composer test` then checks that nothing drifted.

require dirname(__DIR__) . '/vendor/autoload.php';

$changed = Zana\Docs\Tests\Examples::sync();
echo $changed === [] ? "All pages are in sync.\n" : 'Updated: ' . implode(', ', $changed) . "\n";

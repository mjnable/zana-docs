<?php

declare(strict_types=1);

// Writes llms.txt, the entry point for AI coding agents, from the pages (FE-48).
// With --full <file>, writes every page into one file instead, for the GitHub Release.

require dirname(__DIR__) . '/vendor/autoload.php';

use Zana\Docs\Tests\Llms;

$full = array_search('--full', $argv, true);
if ($full !== false) {
    $file = $argv[$full + 1] ?? throw new InvalidArgumentException('--full needs a file name.');
    is_dir(dirname($file)) || mkdir(dirname($file), 0777, true);
    file_put_contents($file, Llms::full()) !== false || exit(1);
    echo "Wrote {$file}.\n";
    exit(0);
}

file_put_contents(dirname(__DIR__) . '/llms.txt', Llms::index());
echo "Wrote llms.txt for Zana ", Llms::version(), ".\n";

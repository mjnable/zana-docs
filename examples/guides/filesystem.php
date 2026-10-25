<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Filesystem\FilesystemInterface;
use Zana\Filesystem\InMemoryFilesystem;

// region: files
function exportReport(FilesystemInterface $storage): void   // LocalFilesystem in production
{
    $storage->write('reports/2026/october.csv', "patient,bmi\nAda,22.49\n");
    $storage->write('reports/2026/november.csv', "patient,bmi\n");
    $storage->copy('reports/2026/october.csv', 'archive/october.csv');
}

$storage = new InMemoryFilesystem(url: 'https://files.example.com');
exportReport($storage);

echo $storage->read('reports/2026/october.csv');
echo $storage->size('reports/2026/october.csv'), ' bytes, ', $storage->mimeType('archive/october.csv'), "\n";
echo implode(', ', $storage->listContents('reports', recursive: true)), "\n";
echo $storage->url('archive/october.csv'), "\n";

$storage->deleteDirectory('reports');
var_export($storage->exists('reports/2026/october.csv'));
echo "\n";
// endregion: files

<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Maker\GeneratedFile;
use Zana\Maker\Generator;

$base = sys_get_temp_dir() . '/zana-docs-maker-' . bin2hex(random_bytes(4));
mkdir($base);

// region: generate
// The make:* commands do this; here the Generator is used directly.
// A plan is a snapshot of the files: write each plan before making the next one.
$generator = new Generator($base);
$steps = [
    'make:module Clinics' => static fn (): array => $generator->planModule('Clinics'),
    'make:entity Clinics Clinic' => static fn (): array => $generator->planEntity('Clinics', 'Clinic'),
    'make:feature Clinics OpenClinic' => static fn (): array => $generator->planFeature('Clinics', 'OpenClinic'),
    'make:controller Clinics Clinics' => static fn (): array => $generator->planController('Clinics', 'Clinics'),
];
foreach ($steps as $command => $plan) {
    $files = $plan();
    $generator->write($files);
    echo $command, "\n";
    foreach (array_filter($files, static fn (GeneratedFile $f): bool => !str_ends_with($f->relativePath, '.gitkeep')) as $file) {
        echo '  ', $file->update ? 'updated ' : 'created ', $file->relativePath, "\n";
    }
}
// endregion: generate

// region: wiring
$module = (string) file_get_contents("{$base}/modules/Clinics/ClinicsModule.php");
foreach (explode("\n", $module) as $line) {
    if (str_starts_with(ltrim($line), '$')) {     // the lines maker added to register() and boot()
        echo trim($line), "\n";
    }
}
// endregion: wiring

exec(PHP_OS_FAMILY === 'Windows' ? 'rmdir /s /q ' . escapeshellarg(str_replace('/', '\\', $base)) : 'rm -rf ' . escapeshellarg($base));

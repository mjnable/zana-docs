<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Configuration\ConfigurationBuilder;

putenv('APP_DATABASE__CONNECTIONS__MAIN__DATABASE=var/test.sqlite');
putenv('APP_FEATURES__REGISTRATION=false');

// region: build
$config = (new ConfigurationBuilder())
    ->addArray([                                   // like config/database.php
        'default' => 'main',
        'connections' => [
            'main' => ['driver' => 'sqlite', 'database' => 'var/app.sqlite'],
        ],
    ], 'database')
    ->addArray(['registration' => true, 'max_upload_mb' => '8'], 'features')
    ->addEnvironment()                             // APP_A__B__C overrides a.b.c
    ->build();
// endregion: build

// region: read
echo $config->string('database.default'), "\n";
echo $config->string('database.connections.main.database'), "\n";   // overridden by the environment
echo var_export($config->bool('features.registration'), true), "\n";  // "false" from the environment
echo $config->int('features.max_upload_mb') * 2, "\n";               // numeric strings convert
echo $config->string('features.theme', 'light'), "\n";               // a default for missing keys

$database = $config->section('database');
echo $database->string('connections.main.driver'), "\n";
// endregion: read

// region: typed
try {
    $config->int('database.default');
} catch (\Throwable $e) {
    echo get_class($e), ': ', $e->getMessage(), "\n";
}
// endregion: typed

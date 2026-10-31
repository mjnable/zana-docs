<?php

declare(strict_types=1);

// Each test gets a fresh database, migrated by the test itself.
return [
    'connections' => [
        'main' => ['driver' => 'sqlite', 'database' => ':memory:'],
    ],
];

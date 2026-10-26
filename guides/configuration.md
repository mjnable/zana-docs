# Configuration

`mj-zana/configuration` merges settings from PHP files, the environment and
`.env` into one read-only, typed configuration. In an application,
`zana/core` builds it for you. This guide shows how the layers combine.

```
composer require mj-zana/configuration
```

## Layers

<!-- example: examples/guides/configuration.php#build -->
```php
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
```

Later layers override earlier ones. Associative arrays merge key by key;
lists and scalar values are replaced whole. With
`APP_DATABASE__CONNECTIONS__MAIN__DATABASE=var/test.sqlite` and
`APP_FEATURES__REGISTRATION=false` in the environment:

<!-- example: examples/guides/configuration.php#read -->
```php
echo $config->string('database.default'), "\n";
echo $config->string('database.connections.main.database'), "\n";   // overridden by the environment
echo var_export($config->bool('features.registration'), true), "\n";  // "false" from the environment
echo $config->int('features.max_upload_mb') * 2, "\n";               // numeric strings convert
echo $config->string('features.theme', 'light'), "\n";               // a default for missing keys

$database = $config->section('database');
echo $database->string('connections.main.driver'), "\n";
```

Configuration files return plain values: strings, numbers, booleans,
null, arrays and enums. Secrets and server addresses do not belong in them;
set `APP_A__B` variables on the server, which override `a.b`. Both rules
let the application cache its configuration in production (see
[core](core.md#new-in-24-the-configuration-cache)).

## Typed reads fail loudly

<!-- example: examples/guides/configuration.php#typed -->
```php
try {
    $config->int('database.default');
} catch (\Throwable $e) {
    echo get_class($e), ': ', $e->getMessage(), "\n";
}
```

<!-- example: examples/guides/configuration.out -->
```text
main
var/test.sqlite
false
16
light
sqlite
Zana\Configuration\ConfigurationException: Configuration key "database.default" should be an int, got string.
```

A missing key without a default fails too, so a typo in a key name shows up
at once rather than as a silent `null`.

## In an application

- **Files:** `config/<name>.php` returns an array and becomes the
  `<name>.*` section. `config/<environment>/<name>.php` overrides it, e.g.
  `config/testing/logging.php`.
- **Modules:** a module's `Configuration/<name>.php` becomes
  `<module>.<name>.*`.
- **Environment:** `APP_*` variables come last. `__` separates levels, and
  `true`, `false`, `null` and numbers are converted.
- **`.env`:** loaded first, if present. It never overrides variables that
  are already set.
- **Injection:** inject `ConfigurationInterface`, or a single value with
  `#[Config('greetings.default_name')]` on a constructor parameter.
- **Keep reading in composition code.** Read settings in providers and
  module factories, and hand plain values to your application code. The
  [getting-started](../getting-started.md) module shows the pattern.
- **Production:** `ConfigurationCache` can store the merged values in one
  file.

Reference: the [configuration README](https://github.com/mjnable/zana-configuration).

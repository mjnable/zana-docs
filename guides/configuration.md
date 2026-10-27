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
  values are converted (see [values from the environment](#values-from-the-environment)).
- **`.env`:** loaded first, if present. It never overrides variables that
  are already set.
- **Injection:** inject `ConfigurationInterface`, or a single value with
  `#[Config('greetings.default_name')]` on a constructor parameter.
- **Keep reading in composition code.** Read settings in providers and
  module factories, and hand plain values to your application code. The
  [getting-started](../getting-started.md) module shows the pattern.
- **Production:** `ApplicationBuilder::withConfigurationCache()` keeps the
  values of the configuration files in one file (see
  [core](core.md#new-in-24-the-configuration-cache)).

## Values from the environment

Environment variables are text; `APP_*` values are converted so that
`APP_SHOP__OPEN=true` reads as a bool. A value that is text even when made
of digits, such as a phone number or a password, stays text (2.5.1):

<!-- example: examples/guides/env-values.php#env -->
```php
file_put_contents($file, <<<'ENV'
    APP_SHOP__WHATSAPP="+243812345678"   # quoted: always text
    APP_MAIL__PASSWORD='20261027'
    APP_SHOP__PHONE=0812345678           # unquoted, but a leading 0 keeps it text
    APP_SHOP__ITEMS_PER_PAGE=24          # unquoted numbers and booleans convert
    APP_SHOP__OPEN=true
    ENV);
DotEnv::load($file);

$config = (new ConfigurationBuilder())->addEnvironment()->build();
foreach (['shop.whatsapp', 'mail.password', 'shop.phone', 'shop.items_per_page', 'shop.open'] as $key) {
    printf("%-20s %s\n", $key, var_export($config->get($key), true));
}

echo $config->int('mail.password'), ' ', $config->string('shop.items_per_page'), "\n";   // typed reads convert text and numbers
```

<!-- example: examples/guides/env-values.out -->
```text
shop.whatsapp        '+243812345678'
mail.password        '20261027'
shop.phone           '0812345678'
shop.items_per_page  24
shop.open            true
20261027 24
```

- **Quoted values are text,** in single or double quotes. Quote passwords,
  phone numbers, identifiers and anything else that is not a number.
- **Unquoted values convert:** `true`, `false` and `null` (any case),
  integers and decimals (`24`, `-3`, `0.5`, `2e3`).
- **Except** a leading `+` (`+243812345678`), a leading zero (`007`,
  `0812345678`) and integers longer than 15 digits: those stay text, as a
  number would change them.
- **Typed reads accept both:** `string()` returns an integer as text,
  `int()` and `float()` read numeric text, and `bool()` reads `"true"`,
  `"false"`, `"1"` and `"0"`. `#[Config]` injection converts to the
  parameter's type the same way.
- Only `.env` values can be quoted. A variable set by the server has no
  quotes, so it follows the rules for unquoted values.

Reference: the [configuration README](https://github.com/mjnable/zana-configuration).

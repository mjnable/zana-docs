<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Configuration\ConfigurationBuilder;
use Zana\Configuration\DotEnv;

$file = sys_get_temp_dir() . '/zana-docs-env-' . bin2hex(random_bytes(4));

// region: env
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
// endregion: env

unlink($file);

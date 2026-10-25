<?php

declare(strict_types=1);

use Website\Bootstrap;
use Zana\Http\HttpKernel;

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

// If the application cannot start (missing configuration, a fatal deployment
// check), visitors get public/503.html and the reason goes to PHP's error log.
HttpKernel::run(Bootstrap::builder($base)->withContainerCache($base . '/var/cache/container.php'));

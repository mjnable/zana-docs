<?php

declare(strict_types=1);

use Hello\Bootstrap;
use Zana\Http\HttpKernel;

require dirname(__DIR__, 4) . '/vendor/autoload.php'; // in your project: dirname(__DIR__) . '/vendor/autoload.php'

HttpKernel::serve(Bootstrap::builder(dirname(__DIR__))->build());

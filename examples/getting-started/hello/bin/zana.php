<?php

declare(strict_types=1);

use Hello\Bootstrap;
use Zana\Console\ConsoleKernel;

require dirname(__DIR__, 4) . '/vendor/autoload.php'; // in your project: dirname(__DIR__) . '/vendor/autoload.php'

exit(ConsoleKernel::runApplication(Bootstrap::builder(dirname(__DIR__))->build()));

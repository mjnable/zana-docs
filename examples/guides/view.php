<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\View\Markup;
use Zana\View\PhpEngine;
use Zana\View\TemplatePaths;
use Zana\View\ViewHelpers;

// region: engine
$paths = (new TemplatePaths())->add(__DIR__ . '/templates');          // ViewServiceProvider adds templates/ and modules
$helpers = (new ViewHelpers())->add('upper', static fn (string $text): string => strtoupper($text));
$views = new PhpEngine($paths, $helpers);

echo $views->render('page', [
    'title' => 'Tom & Jerry',
    'tags' => ['<cats>', 'mice'],
    'intro' => new Markup('<em>Already safe</em>'),
]);
// endregion: engine

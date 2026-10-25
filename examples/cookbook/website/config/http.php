<?php

use Zana\Http\SecurityHeadersMiddleware;
use Zana\Translation\LocaleMiddleware;

return [
    'middleware' => [SecurityHeadersMiddleware::class, LocaleMiddleware::class],
    'security_headers' => [
        'csp' => [
            'script-src' => ["'self'", '{nonce}'],
            'img-src' => ["'self'", 'data:'],
        ],
    ],
];

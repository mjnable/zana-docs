<?php

return [
    // A placeholder: production refuses to start until APP_SECURITY__SECRET replaces it.
    'secret' => 'change-me-to-64-random-hex-characters-generated-on-the-server',

    // Bot traps on the contact form, without a CAPTCHA (csrf_field() prints the honeypot).
    'csrf' => [
        'honeypot' => 'website',                  // a hidden field people never fill in
        'minimum_age' => 3,                       // seconds: no person writes a message faster
        'spam_redirect' => '/en/contact/thanks',  // bots are told it worked, and nothing is sent
    ],
];

<?php

// Rate limiting counts attempts here, so it must outlive a request: files under var/, which
// works on shared hosting. (The default, "array", forgets everything after each request.)
return [
    'default' => 'file',
    'stores' => [
        'file' => ['driver' => 'file', 'path' => 'var/cache/data', 'ttl' => 3600],
    ],
];

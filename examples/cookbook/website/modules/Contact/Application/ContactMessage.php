<?php

declare(strict_types=1);

namespace Modules\Contact\Application;

use Zana\Validation\Constraint\Email;
use Zana\Validation\Constraint\Length;
use Zana\Validation\Constraint\NotBlank;

/**
 * What a visitor sends. The messages are translation keys.
 */
final readonly class ContactMessage
{
    public function __construct(
        #[NotBlank(message: 'contact.error.name')]
        #[Length(max: 100)]
        public string $name,
        #[NotBlank(message: 'contact.error.email')]
        #[Email(message: 'contact.error.email')]
        public string $email,
        #[Length(min: 10, max: 5000, message: 'contact.error.message')]
        public string $message,
    ) {
    }
}

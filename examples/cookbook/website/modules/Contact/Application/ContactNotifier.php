<?php

declare(strict_types=1);

namespace Modules\Contact\Application;

/**
 * Tells the company about a message; the infrastructure decides how.
 */
interface ContactNotifier
{
    public function notify(ContactMessage $message): void;
}

<?php

declare(strict_types=1);

namespace Modules\Appointments\Application;

/**
 * The booking was refused: an unknown patient or a domain rule. Carries field errors.
 */
final class AppointmentRejected extends \RuntimeException
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(public readonly array $errors, ?\Throwable $previous = null)
    {
        parent::__construct(implode(' ', $errors), 0, $previous);
    }
}

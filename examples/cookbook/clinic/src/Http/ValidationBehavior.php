<?php

declare(strict_types=1);

namespace Clinic\Http;

use Zana\Mediator\PipelineBehavior;
use Zana\Validation\Validator;

/**
 * Validates every command and query against its constraints before the handler runs.
 */
final readonly class ValidationBehavior implements PipelineBehavior
{
    public function __construct(private Validator $validator)
    {
    }

    public function handle(object $message, \Closure $next): mixed
    {
        $this->validator->assertValid($message);

        return $next($message);
    }
}

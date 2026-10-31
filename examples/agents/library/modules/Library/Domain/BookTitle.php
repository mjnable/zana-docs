<?php

declare(strict_types=1);

namespace Modules\Library\Domain;

use Zana\Ddd\Domain\DomainException;
use Zana\Ddd\Domain\ValueObject;

/**
 * Book title: not empty, without surrounding spaces.
 * Replace this rule with the real one, and update its test.
 */
final readonly class BookTitle extends ValueObject
{
    public string $value;

    /**
     * @throws DomainException
     */
    public function __construct(string $value)
    {
        $value = trim($value);
        if ($value === '') {
            throw new DomainException('Book title must not be empty.');
        }

        $this->value = $value;
    }
}

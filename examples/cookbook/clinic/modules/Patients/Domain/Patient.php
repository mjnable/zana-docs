<?php

declare(strict_types=1);

namespace Modules\Patients\Domain;

use Zana\Ddd\Domain\AggregateRoot;
use Zana\Ddd\Domain\DomainException;

final class Patient extends AggregateRoot
{
    private function __construct(PatientId $id, public readonly string $name)
    {
        parent::__construct($id);
    }

    public static function register(PatientId $id, string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new DomainException('A patient needs a name.');
        }

        return new self($id, $name);
    }

    public static function restore(PatientId $id, string $name): self
    {
        return new self($id, $name);
    }

    public function id(): PatientId
    {
        assert($this->id instanceof PatientId);

        return $this->id;
    }
}

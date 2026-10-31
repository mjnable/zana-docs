<?php

declare(strict_types=1);

namespace Modules\Library\Domain;

use Zana\Ddd\Domain\AggregateRoot;

final class Book extends AggregateRoot
{
    // region: entity
    private function __construct(
        BookId $id,
        public private(set) BookTitle $title,
        public private(set) string $author,
    ) {
        parent::__construct($id);
    }

    public static function create(BookTitle $title, string $author): self
    {
        return new self(BookId::generate(), $title, $author);
    }

    /**
     * Rebuild a stored Book (used by its repository); records no event.
     */
    public static function restore(BookId $id, int $version, BookTitle $title, string $author): self
    {
        $entity = new self($id, $title, $author);
        $entity->version = $version;

        return $entity;
    }
    // endregion: entity

    public function id(): BookId
    {
        assert($this->id instanceof BookId);

        return $this->id;
    }
}

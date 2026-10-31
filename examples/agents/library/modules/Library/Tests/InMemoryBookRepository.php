<?php

declare(strict_types=1);

namespace Modules\Library\Tests;

use Modules\Library\Domain\Book;
use Modules\Library\Domain\BookRepositoryInterface;
use Zana\Ddd\Domain\AggregateRoot;
use Zana\Ddd\Domain\EntityNotFoundException;

/**
 * Books in memory, for the handler tests.
 */
final class InMemoryBookRepository implements BookRepositoryInterface
{
    /** @var array<string, Book> */
    private array $books = [];

    public function find(mixed $id): ?Book
    {
        return $this->books[(string) $id] ?? null;
    }

    public function get(mixed $id): Book
    {
        return $this->find($id) ?? throw new EntityNotFoundException("There is no book {$id}.");
    }

    public function add(AggregateRoot $aggregate): void
    {
        assert($aggregate instanceof Book);
        $this->books[(string) $aggregate->id()] = $aggregate;
    }

    public function remove(AggregateRoot $aggregate): void
    {
        assert($aggregate instanceof Book);
        unset($this->books[(string) $aggregate->id()]);
    }

    public function all(): array
    {
        $books = array_values($this->books);
        usort($books, static fn (Book $a, Book $b): int => $a->title->value <=> $b->title->value);

        return $books;
    }
}

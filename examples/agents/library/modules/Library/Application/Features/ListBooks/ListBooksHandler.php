<?php

declare(strict_types=1);

namespace Modules\Library\Application\Features\ListBooks;

use Modules\Library\Domain\Book;
use Modules\Library\Domain\BookRepositoryInterface;
use Zana\Mediator\Attribute\Handles;

#[Handles(ListBooksQuery::class)]
final readonly class ListBooksHandler
{
    public function __construct(private BookRepositoryInterface $books)
    {
    }

    /**
     * @return list<array{id: string, title: string, author: string}>
     */
    public function __invoke(ListBooksQuery $query): array
    {
        return array_map(static fn (Book $book): array => [
            'id' => (string) $book->id(),
            'title' => $book->title->value,
            'author' => $book->author,
        ], $this->books->all());
    }
}

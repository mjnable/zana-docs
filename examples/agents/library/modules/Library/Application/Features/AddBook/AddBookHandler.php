<?php

declare(strict_types=1);

namespace Modules\Library\Application\Features\AddBook;

use Modules\Library\Domain\Book;
use Modules\Library\Domain\BookRepositoryInterface;
use Modules\Library\Domain\BookTitle;
use Zana\Mediator\Attribute\Handles;

// region: handler
#[Handles(AddBookCommand::class)]
final readonly class AddBookHandler
{
    public function __construct(private BookRepositoryInterface $books)
    {
    }

    public function __invoke(AddBookCommand $command): void
    {
        $this->books->add(Book::create(new BookTitle($command->title), $command->author));
    }
}
// endregion: handler

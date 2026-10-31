<?php

declare(strict_types=1);

namespace Modules\Library\Tests\Application;

use Modules\Library\Application\Features\ListBooks\ListBooksHandler;
use Modules\Library\Application\Features\ListBooks\ListBooksQuery;
use Modules\Library\Domain\Book;
use Modules\Library\Domain\BookTitle;
use Modules\Library\Tests\InMemoryBookRepository;
use PHPUnit\Framework\TestCase;

final class ListBooksHandlerTest extends TestCase
{
    public function testItListsTheBooksByTitle(): void
    {
        $books = new InMemoryBookRepository();
        $books->add(Book::create(new BookTitle('Neuromancer'), 'William Gibson'));
        $books->add(Book::create(new BookTitle('Dune'), 'Frank Herbert'));

        $list = (new ListBooksHandler($books))(new ListBooksQuery());

        $this->assertSame(['Dune', 'Neuromancer'], array_column($list, 'title'));
    }
}

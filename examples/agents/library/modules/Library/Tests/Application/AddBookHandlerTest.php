<?php

declare(strict_types=1);

namespace Modules\Library\Tests\Application;

use Modules\Library\Application\Features\AddBook\AddBookCommand;
use Modules\Library\Application\Features\AddBook\AddBookHandler;
use Modules\Library\Tests\InMemoryBookRepository;
use PHPUnit\Framework\TestCase;
use Zana\Ddd\Domain\DomainException;

final class AddBookHandlerTest extends TestCase
{
    public function testItAddsTheBook(): void
    {
        $books = new InMemoryBookRepository();

        (new AddBookHandler($books))(new AddBookCommand(title: ' Dune ', author: 'Frank Herbert'));

        $this->assertCount(1, $books->all());
        $this->assertSame('Dune', $books->all()[0]->title->value, 'the title is trimmed');
        $this->assertSame('Frank Herbert', $books->all()[0]->author);
    }

    public function testATitleOfSpacesIsRefused(): void
    {
        $this->expectException(DomainException::class);

        (new AddBookHandler(new InMemoryBookRepository()))(new AddBookCommand(title: '   ', author: 'Anonymous'));
    }
}

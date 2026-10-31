<?php

declare(strict_types=1);

namespace Modules\Library\Tests\Domain;

use Modules\Library\Domain\BookTitle;
use PHPUnit\Framework\TestCase;
use Zana\Ddd\Domain\DomainException;

final class BookTitleTest extends TestCase
{
    public function testItIsComparedByValue(): void
    {
        $this->assertTrue((new BookTitle('Example'))->equals(new BookTitle('Example')));
        $this->assertFalse((new BookTitle('Example'))->equals(new BookTitle('Other')));
    }

    public function testAnInvalidValueIsRefused(): void
    {
        $this->expectException(DomainException::class);

        new BookTitle('  ');
    }
}

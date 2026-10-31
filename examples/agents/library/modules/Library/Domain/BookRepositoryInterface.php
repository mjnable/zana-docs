<?php

declare(strict_types=1);

namespace Modules\Library\Domain;

use Zana\Ddd\Domain\RepositoryInterface;

/**
 * @extends RepositoryInterface<Book>
 */
interface BookRepositoryInterface extends RepositoryInterface
{
    /**
     * @return list<Book> by title
     */
    public function all(): array;
}

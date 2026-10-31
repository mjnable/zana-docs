<?php

declare(strict_types=1);

namespace Modules\Library\Application\Features\ListBooks;

use Zana\Ddd\Application\Query;

final class ListBooksQuery extends Query
{
    public function __construct()
    {
        parent::__construct();
    }
}

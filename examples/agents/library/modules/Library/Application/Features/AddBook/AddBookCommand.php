<?php

declare(strict_types=1);

namespace Modules\Library\Application\Features\AddBook;

use Zana\Ddd\Application\Command;
use Zana\Validation\Constraint\NotBlank;

final class AddBookCommand extends Command
{
    public function __construct(
        #[NotBlank]
        public readonly string $title,
        #[NotBlank]
        public readonly string $author,
    ) {
        parent::__construct();
    }
}

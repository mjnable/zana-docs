<?php

declare(strict_types=1);

namespace Modules\Greetings\Presentation\Api;

use Modules\Greetings\Application\Greeter;

final readonly class GreetAction
{
    public function __construct(private Greeter $greeter)
    {
    }

    /**
     * GET /hello and GET /hello/{name}. An array becomes a JSON response.
     *
     * @return array{message: string}
     */
    public function __invoke(?string $name = null): array
    {
        return ['message' => $this->greeter->greet($name)];
    }
}

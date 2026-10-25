<?php

declare(strict_types=1);

namespace Modules\Greetings\Application;

final readonly class Greeter
{
    public function __construct(private string $defaultName)
    {
    }

    public function greet(?string $name = null): string
    {
        $name ??= $this->defaultName;

        return "Hello, {$name}!";
    }
}

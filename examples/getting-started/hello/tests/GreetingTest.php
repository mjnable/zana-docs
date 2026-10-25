<?php

declare(strict_types=1);

namespace Hello\Tests;

use Hello\Bootstrap;
use Zana\Core\ApplicationInterface;
use Zana\Testing\ApplicationTestCase;

final class GreetingTest extends ApplicationTestCase
{
    protected function createApplication(): ApplicationInterface
    {
        return Bootstrap::builder(dirname(__DIR__))->withEnvironment('testing')->build();
    }

    public function testTheGreetingOverHttp(): void
    {
        $this->http()->get('/hello')->assertOk()->assertJson(['message' => 'Hello, world!']);
        $this->http()->get('/hello/Ada')->assertOk()->assertJsonPath('message', 'Hello, Ada!');
    }

    public function testTheGreetingFromTheCommandLine(): void
    {
        $this->console()->run('greet Grace')->assertSuccessful()->assertOutputContains('Hello, Grace!');
    }
}

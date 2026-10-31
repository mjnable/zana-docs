<?php

declare(strict_types=1);

namespace Modules\Library\Tests\Presentation;

use App\Bootstrap;
use Zana\Core\ApplicationBuilder;
use Zana\Testing\ApplicationTestCase;

final class ListBooksApiTest extends ApplicationTestCase
{
    protected function createApplicationBuilder(): ApplicationBuilder
    {
        return Bootstrap::builder(dirname(__DIR__, 4))->withEnvironment('testing');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(0, $this->console()->run('migrate')->exitCode(), 'a fresh database (config/testing)');
    }

    public function testListBooks(): void
    {
        $this->http()->get('/api/library/list-books')->assertOk()->assertJsonPath('data', []);
    }
}

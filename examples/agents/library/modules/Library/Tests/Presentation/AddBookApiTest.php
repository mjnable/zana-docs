<?php

declare(strict_types=1);

namespace Modules\Library\Tests\Presentation;

use App\Bootstrap;
use Zana\Core\ApplicationBuilder;
use Zana\Testing\ApplicationTestCase;

final class AddBookApiTest extends ApplicationTestCase
{
    protected function createApplicationBuilder(): ApplicationBuilder
    {
        return Bootstrap::builder(dirname(__DIR__, 4))->withEnvironment('testing');
    }

    // region: migrate
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(0, $this->console()->run('migrate')->exitCode(), 'a fresh database (config/testing)');
    }
    // endregion: migrate

    public function testAddBook(): void
    {
        $this->http()->post('/api/library/add-book', ['title' => 'Dune', 'author' => 'Frank Herbert'])->assertNoContent();

        $this->http()->get('/api/library/list-books')->assertOk()->assertJsonPath('data.0.title', 'Dune');
    }

    public function testMissingFieldsAreRefused(): void
    {
        $errors = $this->http()->post('/api/library/add-book', [])->assertUnprocessable()->jsonPath('errors');

        $this->assertIsArray($errors);
        $this->assertArrayHasKey('title', $errors);
    }
}

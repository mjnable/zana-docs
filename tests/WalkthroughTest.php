<?php

declare(strict_types=1);

namespace Zana\Docs\Tests;

use App\Bootstrap;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Zana\Console\ConsoleKernel;
use Zana\Console\ConsoleServiceProvider;
use Zana\Core\Application;
use Zana\Maker\MakerServiceProvider;
use Zana\Maker\Plan;
use Zana\Maker\PlanRunner;
use Zana\Testing\ConsoleTester;

/**
 * FE-49: the walkthrough of "Working with AI coding agents" is what built
 * examples/agents/library. Its make: commands, run in an empty application,
 * write every file of the example's module, and the files the page does not
 * say were written by hand are exactly as generated. The example passes
 * `check`; its own tests run in the "examples" suite.
 */
final class WalkthroughTest extends TestCase
{
    private const string PAGE = 'ai-agents.md';

    public const string EXAMPLE = 'examples/agents/library';

    /** The files the page says were written by hand after generating, under modules/Library/. */
    private const array EDITED = [
        'Domain/Book.php',
        'Domain/BookRepositoryInterface.php',
        'Infrastructure/Persistence/DatabaseBookRepository.php',
        'Infrastructure/Persistence/Migrations/create_books_table.php',
        'Application/Features/AddBook/AddBookHandler.php',
        'Application/Features/ListBooks/ListBooksHandler.php',
        'Tests/Application/AddBookHandlerTest.php',
        'Tests/Application/ListBooksHandlerTest.php',
        'Tests/Presentation/AddBookApiTest.php',
        'Tests/Presentation/ListBooksApiTest.php',
    ];

    /** Files the page says were added by hand. */
    private const array ADDED = ['Tests/InMemoryBookRepository.php'];

    private string $base = '';

    #[After]
    public function removeBase(): void
    {
        if ($this->base !== '') {
            exec(PHP_OS_FAMILY === 'Windows' ? 'rmdir /s /q ' . escapeshellarg(str_replace('/', '\\', $this->base)) : 'rm -rf ' . escapeshellarg($this->base));
        }
    }

    public function testTheCommandsOfThePageBuildTheExample(): void
    {
        $this->base = str_replace('\\', '/', sys_get_temp_dir()) . '/zana-docs-walkthrough-' . bin2hex(random_bytes(4));
        mkdir($this->base);
        $console = new ConsoleTester(Application::configure($this->base)
            ->withProviders(ConsoleServiceProvider::class, MakerServiceProvider::class)
            ->build()
            ->container()
            ->get(ConsoleKernel::class));

        $commands = self::commands();
        $this->assertCount(6, $commands);
        foreach ($commands as $command) {
            $result = $console->run($command);
            $this->assertSame(0, $result->exitCode(), "{$command}\n{$result->output()}");
        }

        $generated = self::files($this->base);
        $example = self::files(Examples::ROOT . '/' . self::EXAMPLE);
        $this->assertSame(
            array_keys($generated),
            array_values(array_diff(array_keys($example), array_map(static fn (string $file): string => "modules/Library/{$file}", self::ADDED))),
            'the example has every generated file, and only the files the page adds',
        );

        $page = (string) file_get_contents(Examples::ROOT . '/' . self::PAGE);
        foreach ($generated as $path => $contents) {
            $file = substr($path, strlen('modules/Library/'));
            if (in_array($file, self::EDITED, true)) {
                $this->assertNotSame($contents, $example[$path], "{$path} is said to be written by hand");
                $this->assertStringContainsString(str_contains($file, 'Migrations') ? 'the migration' : (str_starts_with($file, 'Tests/') ? 'the four tests' : $file), $page);
            } else {
                $this->assertSame($contents, $example[$path], "{$path} is as generated");
            }
        }
        foreach (self::ADDED as $file) {
            $this->assertStringContainsString($file, $page);
        }

        // FE-51: the plan file shown on the page stands for the same commands and writes the same files.
        $plan = Plan::fromJson((string) file_get_contents(Examples::ROOT . '/' . self::EXAMPLE . '/library.plan.json'));
        $this->assertSame($commands, $plan->commands());
        $planned = $this->base . '-plan';
        mkdir($planned);
        try {
            (new PlanRunner($planned))->run($plan);
            $this->assertSame($generated, self::files($planned));
        } finally {
            exec(PHP_OS_FAMILY === 'Windows' ? 'rmdir /s /q ' . escapeshellarg(str_replace('/', '\\', $planned)) : 'rm -rf ' . escapeshellarg($planned));
        }
    }

    public function testTheExamplePassesCheck(): void
    {
        $app = Bootstrap::builder(Examples::ROOT . '/' . self::EXAMPLE)->withEnvironment('testing')->build();
        $console = new ConsoleTester($app->container()->get(ConsoleKernel::class));

        $this->assertSame(0, $console->run('migrate')->exitCode());
        $check = $console->run('check --format=json');
        $report = json_decode($check->output(), true);

        $this->assertIsArray($report, $check->output());
        $this->assertSame('ok', $report['status'], $check->output());
        $this->assertSame([], $report['findings']);
        $this->assertContains('Architecture: 1 module(s), no violation', $report['passed']);
        $app->terminate();
    }

    /**
     * The make: commands of the walkthrough section, without "php bin/console".
     *
     * @return list<string>
     */
    private static function commands(): array
    {
        $page = str_replace("\r\n", "\n", (string) file_get_contents(Examples::ROOT . '/' . self::PAGE));
        preg_match('/\n## A walkthrough.*?(?=\n## |$)/s', $page, $section);
        preg_match_all('/^php bin\/console (make:.+)$/m', $section[0] ?? '', $matches);

        // make:plan stands for the same commands (checked separately).
        return array_values(array_filter($matches[1], static fn (string $command): bool => !str_starts_with($command, 'make:plan')));
    }

    /**
     * @return array<string, string> path under the application (migration timestamps removed) => contents
     */
    private static function files(string $base): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("{$base}/modules", \FilesystemIterator::SKIP_DOTS));
        foreach ([...iterator_to_array($iterator, false), new \SplFileInfo("{$base}/config/modules.php")] as $file) {
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($base) + 1);
            $files[(string) preg_replace('/\d{4}_\d{2}_\d{2}_\d{6}_/', '', $relative)] = str_replace("\r\n", "\n", (string) file_get_contents($file->getPathname()));
        }
        ksort($files);

        return $files;
    }
}

<?php

declare(strict_types=1);

namespace Zana\Docs\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Keeps the documentation honest: every embedded code block matches its
 * example source (see Examples; fix drift with bin/sync-examples.php), every
 * example is shown somewhere, and every examples/guides script prints its
 * .out file.
 */
final class DocsTest extends TestCase
{
    public function testEveryEmbeddedExampleMatchesItsSource(): void
    {
        $checked = 0;
        $problems = [];
        foreach (Examples::pages() as $page) {
            foreach (Examples::embedded($page) as $block) {
                $checked++;
                $source = Examples::source($block['reference']);
                $problems[] = match (true) {
                    $block['code'] === null => "{$page}:{$block['line']}: no code block follows the marker.",
                    $source === null => "{$page}:{$block['line']}: {$block['reference']} does not exist.",
                    $source !== $block['code'] => "{$page}:{$block['line']}: the block differs from {$block['reference']} (run bin/sync-examples.php).",
                    default => null,
                };
            }
        }

        $problems = array_values(array_filter($problems));
        $this->assertSame([], $problems, implode("\n", $problems));
        $this->assertGreaterThan(40, $checked, 'the pages embed tested examples');
    }

    public function testEveryExampleIsShownSomewhere(): void
    {
        $shown = [];
        foreach (Examples::pages() as $page) {
            foreach (Examples::embedded($page) as $block) {
                $shown[explode('#', $block['reference'])[0]] = true;
            }
        }

        $this->assertSame([], array_values(array_filter(Examples::sources(), static fn (string $source): bool => !isset($shown[$source]))), 'examples that no page shows');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function scripts(): iterable
    {
        foreach (glob(Examples::ROOT . '/examples/guides/*.php') ?: [] as $script) {
            yield basename($script) => [$script];
        }
    }

    #[DataProvider('scripts')]
    public function testTheExampleRunsAndPrintsWhatTheGuideShows(string $script): void
    {
        $expected = substr($script, 0, -4) . '.out';
        $this->assertFileExists($expected, 'every script has its expected output next to it');

        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $output, $status);
        $actual = implode("\n", $output);

        $this->assertSame(0, $status, $actual);
        $this->assertSame(rtrim(str_replace("\r\n", "\n", (string) file_get_contents($expected))), rtrim($actual));
    }
}

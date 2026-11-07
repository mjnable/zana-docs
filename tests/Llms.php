<?php

declare(strict_types=1);

namespace Zana\Docs\Tests;

/**
 * llms.txt, the entry point for AI coding agents (FE-48): every page of this
 * documentation version, with a one-line description taken from the page
 * itself, linked to this version's branch. Generated, never edited:
 * `php bin/llms.php` writes it, and the tests fail when it is out of date.
 * `php bin/llms.php --full <file>` writes every page into one file, for
 * the GitHub Release of each documentation version.
 */
final class Llms
{
    private const array GROUPS = [
        'Start here' => ['getting-started.md', 'ai-agents.md', 'deployment.md', 'upgrading.md', 'release-notes.md'],
        'Guides' => 'guides/*.md',
        'Cookbook' => 'cookbook/*.md',
        'Contributing' => ['contributing.md', 'SECURITY.md'],
    ];

    /**
     * The Zana minor version these pages document, from composer.json ("^2.7" or "~2.7.0").
     */
    public static function version(): string
    {
        $composer = json_decode((string) file_get_contents(Examples::ROOT . '/composer.json'), true);
        $constraint = is_array($composer) ? (string) ($composer['require']['mj-zana/core'] ?? '') : '';
        if (preg_match('/(\d+)\.(\d+)/', $constraint, $match) !== 1) {
            throw new \RuntimeException('composer.json does not require mj-zana/core with a version.');
        }

        return "{$match[1]}.{$match[2]}";
    }

    public static function index(): string
    {
        $version = self::version();
        $base = "https://raw.githubusercontent.com/mjnable/zana-docs/{$version}";
        $lines = [
            "# Zana {$version} documentation",
            '',
            '> Zana is a modular PHP 8.5 framework for business applications: modules with Domain, Application,',
            '> Infrastructure and Presentation layers, an explicit dependency injection container validated at',
            '> startup, and packages for HTTP, routing, console, database, security, views and more. These pages',
            "> document Zana {$version}; every code example in them is tested against that release.",
            '',
            'In a Zana application, `php bin/console inspect` and `php bin/console check` (with `--format=json`)',
            'show the application and verify it; `AGENTS.md` at its root states its rules.',
        ];
        foreach (self::pages() as $group => $pages) {
            $lines[] = '';
            $lines[] = "## {$group}";
            $lines[] = '';
            foreach ($pages as $page) {
                [$title, $summary] = self::describe($page);
                $lines[] = "- [{$title}]({$base}/{$page})" . ($summary === '' ? '' : ": {$summary}");
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Every page in one file, each preceded by its path.
     */
    public static function full(): string
    {
        $parts = [self::index()];
        foreach (self::pages() as $pages) {
            foreach ($pages as $page) {
                $parts[] = "\n---\n\nFile: {$page}\n\n" . str_replace("\r\n", "\n", (string) file_get_contents(Examples::ROOT . "/{$page}"));
            }
        }

        return implode('', $parts);
    }

    /**
     * @return array<string, list<string>> group => relative paths
     */
    public static function pages(): array
    {
        $pages = [];
        foreach (self::GROUPS as $group => $pattern) {
            $paths = is_array($pattern) ? $pattern : array_map(
                static fn (string $file): string => substr(str_replace('\\', '/', $file), strlen(Examples::ROOT) + 1),
                glob(Examples::ROOT . "/{$pattern}") ?: [],
            );
            $pages[$group] = array_values(array_filter($paths, static fn (string $path): bool => basename($path) !== 'README.md'));
        }

        return $pages;
    }

    /**
     * The page's title, and its first sentence as a summary.
     *
     * @return array{string, string}
     */
    private static function describe(string $page): array
    {
        $text = str_replace("\r\n", "\n", (string) file_get_contents(Examples::ROOT . "/{$page}"));
        preg_match('/^# (.+)$/m', $text, $title);
        $summary = '';
        foreach (preg_split('/\n\s*\n/', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '' || preg_match('/^(#|>|```|<!--|\||- |\d+\.)/', $paragraph) === 1) {
                continue;
            }
            $plain = (string) preg_replace(['/\[([^\]]+)\]\([^)]+\)/', '/\s+/'], ['$1', ' '], $paragraph);
            $summary = preg_match('/^(.+?[.:])(\s|$)/', $plain, $sentence) === 1 ? rtrim($sentence[1], ':') . (str_ends_with($sentence[1], ':') ? '.' : '') : $plain;
            break;
        }

        return [trim($title[1] ?? basename($page, '.md')), $summary];
    }
}

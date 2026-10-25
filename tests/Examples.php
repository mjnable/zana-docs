<?php

declare(strict_types=1);

namespace Zana\Docs\Tests;

/**
 * Finds the code blocks the pages embed and the example sources they come from.
 *
 * In a page, a code block preceded by <!-- example: path --> or
 * <!-- example: path#region --> shows that file, or the lines between
 * "// region: name" and "// endregion: name" in it (dedented). Region marker
 * lines themselves are never shown.
 */
final class Examples
{
    public const string ROOT = __DIR__ . '/..';

    /**
     * @return list<string> Page paths, relative to the repository root
     */
    public static function pages(): array
    {
        $files = array_merge(glob(self::ROOT . '/*.md') ?: [], glob(self::ROOT . '/guides/*.md') ?: [], glob(self::ROOT . '/cookbook/*.md') ?: []);

        return array_map(static fn (string $file): string => substr($file, strlen(self::ROOT) + 1), $files);
    }

    /**
     * @return list<string> Example source paths, relative to the repository root
     */
    public static function sources(): array
    {
        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT . '/examples', \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'php') {
                $found[] = 'examples/' . str_replace('\\', '/', substr($file->getPathname(), strlen(self::ROOT . '/examples/')));
            }
        }
        sort($found);

        return $found;
    }

    /**
     * @return list<array{line: int, reference: string, start: int, end: int, code: ?string}>
     *         start/end are the indexes of the fence lines; code is null without a block
     */
    public static function embedded(string $page): array
    {
        $lines = self::lines($page);
        $found = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^<!-- example: (\S+) -->$/', trim($line), $m) !== 1) {
                continue;
            }
            $start = $i + 1;
            while (isset($lines[$start]) && trim($lines[$start]) === '') {
                $start++;
            }
            if (!isset($lines[$start]) || !str_starts_with($lines[$start], '```')) {
                $found[] = ['line' => $i + 1, 'reference' => $m[1], 'start' => -1, 'end' => -1, 'code' => null];
                continue;
            }
            $end = $start + 1;
            while (isset($lines[$end]) && !str_starts_with($lines[$end], '```')) {
                $end++;
            }
            $found[] = ['line' => $i + 1, 'reference' => $m[1], 'start' => $start, 'end' => $end, 'code' => implode("\n", array_slice($lines, $start + 1, $end - $start - 1))];
        }

        return $found;
    }

    /**
     * The code a reference shows, or null when the file or region does not exist.
     */
    public static function source(string $reference): ?string
    {
        [$path, $region] = array_pad(explode('#', $reference, 2), 2, null);
        $file = self::ROOT . '/' . $path;
        if (!is_file($file)) {
            return null;
        }
        $lines = preg_split('/\R/', rtrim((string) file_get_contents($file))) ?: [];

        if ($region === null) {
            return implode("\n", array_values(array_filter($lines, static fn (string $line): bool => !self::isMarker($line))));
        }

        $inside = false;
        $selected = [];
        foreach ($lines as $line) {
            if (trim($line) === "// region: {$region}") {
                $inside = true;
                continue;
            }
            if (trim($line) === "// endregion: {$region}") {
                return implode("\n", self::dedent($selected));
            }
            if ($inside && !self::isMarker($line)) {
                $selected[] = $line;
            }
        }

        return null;
    }

    /**
     * Rewrite every embedded block from its source.
     *
     * @return list<string> The pages that changed
     */
    public static function sync(): array
    {
        $changed = [];
        foreach (self::pages() as $page) {
            $lines = self::lines($page);
            $blocks = array_reverse(self::embedded($page));   // from the bottom, so indexes stay valid
            $updated = $lines;
            foreach ($blocks as $block) {
                $source = self::source($block['reference']);
                if ($source === null || $block['code'] === null || $block['code'] === $source) {
                    continue;
                }
                array_splice($updated, $block['start'] + 1, $block['end'] - $block['start'] - 1, explode("\n", $source));
            }
            if ($updated !== $lines) {
                file_put_contents(self::ROOT . '/' . $page, implode("\n", $updated) . "\n");
                $changed[] = $page;
            }
        }

        return $changed;
    }

    /**
     * @return list<string>
     */
    private static function lines(string $page): array
    {
        return preg_split('/\R/', rtrim((string) file_get_contents(self::ROOT . '/' . $page))) ?: [];
    }

    private static function isMarker(string $line): bool
    {
        return preg_match('#^\s*// (end)?region: #', $line) === 1;
    }

    /**
     * @param list<string> $lines
     *
     * @return list<string>
     */
    private static function dedent(array $lines): array
    {
        $indent = PHP_INT_MAX;
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $indent = min($indent, strlen($line) - strlen(ltrim($line, ' ')));
            }
        }

        return array_map(static fn (string $line): string => trim($line) === '' ? '' : substr($line, $indent === PHP_INT_MAX ? 0 : $indent), $lines);
    }
}

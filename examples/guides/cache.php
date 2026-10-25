<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Cache\ArrayCache;

$now = 1_800_000_000;
$clock = static function () use (&$now): int {
    return $now;
};

// region: cache
$cache = new ArrayCache(defaultTtl: 3600, clock: $clock);   // FileCache and NullCache work the same way

$calls = 0;
$report = static function () use (&$calls): array {
    $calls++;

    return ['patients' => 120];
};

$cache->remember('report.daily', 60, $report);   // computed and stored
$cache->remember('report.daily', 60, $report);   // served from the cache
echo "computed {$calls} time(s)\n";

$now += 61;                                       // the 60 seconds are up
$cache->remember('report.daily', 60, $report);
echo "computed {$calls} time(s)\n";

$cache->set('token', 'abc');
echo $cache->pull('token'), ' then ', var_export($cache->get('token', 'gone'), true), "\n";   // pull() removes it

try {
    $cache->get('user:7');                        // PSR-16 reserves {}()/\@:
} catch (\Psr\SimpleCache\InvalidArgumentException $e) {
    echo get_class($e), "\n";
}
// endregion: cache

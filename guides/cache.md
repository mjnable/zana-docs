# Cache

`mj-zana/cache` provides PSR-16 caches with a few additions: `remember()`,
`pull()` and an injectable clock. There are three stores: in memory, in
files, and a null cache that stores nothing.

```
composer require mj-zana/cache
```

## Remember

<!-- example: examples/guides/cache.php#cache -->
```php
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
```

<!-- example: examples/guides/cache.out -->
```text
computed 1 time(s)
computed 2 time(s)
abc then 'gone'
Zana\Cache\InvalidCacheKeyException
```

## In an application

Register `CacheServiceProvider`, inject `Zana\Cache\CacheInterface` (or
`Psr\SimpleCache\CacheInterface`), and configure `config/cache.php`:

```php
return [
    'default' => 'file',
    'stores' => [
        'file' => ['driver' => 'file', 'path' => 'var/cache/data', 'ttl' => 3600],
        'memory' => ['driver' => 'array'],
        'off' => ['driver' => 'null'],
    ],
];
```

- **Stores:** without configuration, the default store is in memory.
  `CacheManager::store('name')` returns any configured store.
- **Testing:** use the `null` store in tests or local development to rule
  the cache out.

## Rules worth knowing

- **Keys:** PSR-16 reserves `{}()/\@:`, so use dots or underscores, e.g.
  `report.daily`. An invalid key throws an exception that implements
  PSR-16's `InvalidArgumentException`.
- **TTLs:** a store's `ttl` is the default lifetime. A per-call TTL, in
  seconds or as a `DateInterval`, overrides it, and `0` or less means
  "expire now".
- **The file store writes atomically**, so concurrent readers never see a
  half-written entry. `clear()` only removes the store's own files.
- **The in-memory store lives for one process.** In PHP-FPM that is one
  request; in a worker, until it restarts.

Reference: the [cache README](https://github.com/mjnable/zana-cache).

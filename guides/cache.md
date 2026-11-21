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
- **Protect the cache directory.** File entries are PHP-serialized. Since
  2.28 they are signed, so a file the application did not write is never
  unserialized ([below](#new-in-228-signed-cache-files)); without a key,
  anyone who can write files there can run code.
- **The in-memory store lives for one process.** In PHP-FPM that is one
  request; in a worker, until it restarts.

## New in 2.26: adding and counting atomically

`get()` then `set()` is two steps: two requests at the same instant can both
find a key empty, or both read the same count. Every Zana store has two
operations that cannot be split (`AtomicCacheInterface`):

```php
$cache->add('order.42.paid', true, 3600);       // true for the first caller only
$hits = $cache->increment('api.hits', 1, 60);   // each caller gets its own number
```

- **`add()`** stores only when the key holds nothing (or something expired),
  and says whether it did.
- **`increment()`** adds to a whole number, created at 0 with the TTL;
  an existing number keeps its expiry, so a counting window does not slide.
- **The file store** locks the key's `.lock` file between the read and the
  write, so processes take turns; it refuses, with an exception, rather
  than run unlocked. The in-memory store needs no lock in its one process.
- Security's rate limits and single-use tokens use them (see
  [security](security.md#new-in-226-limits-that-hold-under-concurrent-requests)).
  A PSR-16 cache from elsewhere may not have them; `check` then warns.

## New in 2.28: signed cache files

A file store's entries are PHP-serialized, and `unserialize()` builds
objects, whose code can run as they are built. So a file written to the
cache directory by anything but the application could run code. Since 2.28
every file is signed, and only a file with a valid signature is unserialized:

- **The key** is `cache.key` (at least 32 characters), or one derived from
  `security.secret`, as the queue and outbox derive theirs. The skeletons
  set `security.secret`, so their caches are signed with nothing to change.
- **A file that is unsigned or signed wrongly** is a miss, and is removed.
  A cache written before 2.28 simply fills again after the upgrade.
- **The signature covers the key too:** a valid file copied to where
  another key's file goes is refused.
- **Without a key**, files are neither signed nor checked, as before, and
  `check` warns (`cache.file_signing`).
- **Not covered:** the compiled container and configuration that
  `cache:warm` writes are PHP files, which PHP includes; whoever can write
  to `var/cache` can still change them.

Reference: the [cache README](https://github.com/mjnable/zana-cache).

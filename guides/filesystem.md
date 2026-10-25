# Filesystem

`mj-zana/filesystem` puts local and in-memory storage behind one interface,
`FilesystemInterface`. Code that writes files can then be tested without
touching the disk.

```
composer require mj-zana/filesystem
```

## Files

<!-- example: examples/guides/filesystem.php#files -->
```php
function exportReport(FilesystemInterface $storage): void   // LocalFilesystem in production
{
    $storage->write('reports/2026/october.csv', "patient,bmi\nAda,22.49\n");
    $storage->write('reports/2026/november.csv', "patient,bmi\n");
    $storage->copy('reports/2026/october.csv', 'archive/october.csv');
}

$storage = new InMemoryFilesystem(url: 'https://files.example.com');
exportReport($storage);

echo $storage->read('reports/2026/october.csv');
echo $storage->size('reports/2026/october.csv'), ' bytes, ', $storage->mimeType('archive/october.csv'), "\n";
echo implode(', ', $storage->listContents('reports', recursive: true)), "\n";
echo $storage->url('archive/october.csv'), "\n";

$storage->deleteDirectory('reports');
var_export($storage->exists('reports/2026/october.csv'));
echo "\n";
```

<!-- example: examples/guides/filesystem.out -->
```text
patient,bmi
Ada,22.49
22 bytes, text/csv
reports/2026/november.csv, reports/2026/october.csv
https://files.example.com/archive/october.csv
false
```

## In an application

Register `FilesystemServiceProvider`, inject `FilesystemInterface` for the
default disk, and configure `config/filesystem.php`:

```php
return [
    'default' => 'local',
    'disks' => [
        'local' => ['driver' => 'local', 'root' => 'var/storage'],                      // relative to the base path
        'public' => ['driver' => 'local', 'root' => 'public/files', 'url' => 'https://example.com/files'],
        'scratch' => ['driver' => 'memory'],
    ],
];
```

- **Disks:** without configuration, the default disk is in memory.
  `StorageManager::disk('public')` returns any configured disk.
- **Uploads:** `UploadedFileStore::store()` saves a PSR-7 uploaded file
  under a generated random name. It can limit the size and the allowed MIME
  types. The type is detected from the stored file's content, falling back
  to its extension, and never taken from what the client declares.
  - **Extensions:** the client's extension is kept, except ones a web
    server could execute (`php`, `phtml`, `phar`, `cgi`, `.htaccess`, …),
    which are dropped.
  - **Still recommended:** pass the allowed MIME types, and keep upload
    disks outside the web root.

## Rules worth knowing

- **Paths are relative to the disk's root.** They are normalised: a
  leading `/` is ignored, `.` and repeated slashes disappear, and a `..`
  that would climb above the root throws `PathTraversalException`.
- **Writes accept strings or streams.** `readStream()` avoids loading
  large files into memory.
- **Directories:** writing creates the parent directories, and
  `listContents()` returns file paths, recursively when asked.
- **URLs:** `url()` needs the disk's `url` setting, and throws without it.
- **Symbolic links:** a local disk follows symbolic links inside its root.
  Don't let untrusted users create links there.

Reference: the [filesystem README](https://github.com/mjnable/zana-filesystem).

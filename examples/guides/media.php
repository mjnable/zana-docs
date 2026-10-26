<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Filesystem\InMemoryFilesystem;
use Zana\Media\MediaLibrary;
use Zana\Media\Picture;
use Zana\Media\Variant;

// A 1200 × 800 photo, as an editor would upload it.
$image = imagecreatetruecolor(1200, 800);
ob_start();
imagejpeg($image);
$photo = (string) ob_get_clean();

// region: store
// MediaServiceProvider builds this from media.* and two filesystem disks.
$library = new MediaLibrary(
    public: new InMemoryFilesystem('https://cdn.acme.example'),
    private: new InMemoryFilesystem(),               // originals: never published
    widths: [480, 960, 1600],
);

$media = $library->store($photo, ['en' => 'The team', 'fr' => 'L’équipe']);

foreach (['avif', 'webp', 'jpeg'] as $format) {
    echo $format, ': ', implode(', ', array_map(static fn (Variant $v): string => "{$v->width}×{$v->height}", $media->variants($format))), "\n";
}

$html = (new Picture($library))->html($media, 'fr', sizes: '(min-width: 60em) 50vw, 100vw');
echo str_replace([$media->id, '><'], ['ID', ">\n<"], $html), "\n";
// endregion: store

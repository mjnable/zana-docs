# Media

`mj-zana/media` (2.2) handles the images editors upload: it keeps the
original out of sight, publishes resized AVIF, WebP and JPEG variants, and
renders a responsive `<picture>` with an alt text per language.

```
composer require mj-zana/media
```

It needs PHP's GD extension. AVIF and WebP variants are skipped when GD
was built without them; the JPEG fallback is always there.

## Storing an image

<!-- example: examples/guides/media.php#store -->
```php
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
```

<!-- example: examples/guides/media.out -->
```text
avif: 480×320, 960×640, 1200×800
webp: 480×320, 960×640, 1200×800
jpeg: 1200×800
<picture>
<source type="image/avif" srcset="https://cdn.acme.example/media/ID/480.avif 480w, https://cdn.acme.example/media/ID/960.avif 960w, https://cdn.acme.example/media/ID/1200.avif 1200w" sizes="(min-width: 60em) 50vw, 100vw">
<source type="image/webp" srcset="https://cdn.acme.example/media/ID/480.webp 480w, https://cdn.acme.example/media/ID/960.webp 960w, https://cdn.acme.example/media/ID/1200.webp 1200w" sizes="(min-width: 60em) 50vw, 100vw">
<img src="https://cdn.acme.example/media/ID/1200.jpg" width="1200" height="800" alt="L’équipe" loading="lazy" decoding="async">
</picture>
```

Rules worth knowing:

- **Never upscaled.** Widths larger than the image become one variant at
  the original width, as the 1600 above did.
- **The original is private.** It is kept on the private disk so you can
  regenerate variants later; only variants go to the public disk.
- **Orientation is fixed.** Photos from phones are rotated according to
  their EXIF data, and the metadata (including location) is not copied to
  the variants.
- **Alt text is required, per locale.** `store()` refuses an image without
  alt text in at least one locale. A locale without its own text gets the
  first one; `describe($media, $alt)` updates them.
- `width` and `height` are always set, so the page does not shift as
  images load.

## In an application

Register `MediaServiceProvider` after `FilesystemServiceProvider` and
configure `config/media.php`:

```php
return [
    'disk' => 'public',            // variants, served by the web server or a CDN
    'private_disk' => 'local',     // originals
    'widths' => [480, 960, 1600],
    'formats' => ['avif', 'webp'], // JPEG is always added as the fallback
    'quality' => 75,
];
```

Inject `MediaLibrary` in the controller that receives the upload; pass it
the `UploadedFileInterface` from the request. Keep the returned
`$media->id` on your record. To show it, pass
`$library->find($post->imageId)` to the template; the view package's
`picture` helper renders it in the request's locale (see
[Views](view.md#assets-and-images)):

```php
<?php if ($image !== null): ?><?= $this->picture($image, sizes: '(min-width: 60em) 50vw, 100vw') ?><?php endif ?>
```

Pass the `Media` object itself, not `toArray()`: strings in arrays are
escaped by the template engine, so the alt text would be escaped twice.

# Views

`mj-zana/view` renders HTML pages from templates with layouts, partials and
escaping by default. Templates are plain PHP; `mj-zana/view-twig` adds Twig.

```
composer require mj-zana/view
composer require mj-zana/view-twig   # optional: Twig templates
```

## Rendering

<!-- example: examples/guides/view.php#engine -->
```php
$paths = (new TemplatePaths())->add(__DIR__ . '/templates');          // ViewServiceProvider adds templates/ and modules
$helpers = (new ViewHelpers())->add('upper', static fn (string $text): string => strtoupper($text));
$views = new PhpEngine($paths, $helpers);

echo $views->render('page', [
    'title' => 'Tom & Jerry',
    'tags' => ['<cats>', 'mice'],
    'intro' => new Markup('<em>Already safe</em>'),
]);
```

<!-- example: examples/guides/templates/page.php -->
```php
<?php $this->layout('layout', ['heading' => $this->upper($this->raw('title'))]) ?>
<p><?= $intro ?></p>
<ul><?php foreach ($tags as $tag): ?><li><?= $tag ?></li><?php endforeach ?></ul>
```

<!-- example: examples/guides/templates/layout.php -->
```php
<h1><?= $heading ?></h1>
<title><?= $title ?></title>
<?= $this->section('content') ?>
```

<!-- example: examples/guides/view.out -->
```text
<h1>TOM &amp; JERRY</h1>
<title>Tom &amp; Jerry</title>
<p><em>Already safe</em></p>
<ul><li>&lt;cats&gt;</li><li>mice</li></ul>
```

- **Escaping by default:** strings in the context are escaped, also inside
  arrays.
  - `Markup` is printed as is.
  - `$this->raw('title')` gives the original value.
  - Objects must be escaped with `$this->e($object)`.
- **Layouts and partials:** a template that calls `layout()` becomes its
  layout's `content` section. `start()`/`stop()` fill other sections, and
  `insert()` renders a partial.
- **Helpers:** called on `$this`; their output is escaped unless the helper
  is declared safe.

## In an application

Register `ViewServiceProvider` after the http and routing providers. Route
handlers then return `View` objects:

```php
return View::make('@Contact/form', ['errors' => $errors])->withStatus(422);
```

- **Folders:** application templates live in `templates/` (`view.path`).
  Each module's `Resources/views/` is available under the module's name, as
  `@Contact/form`.
- **Helpers:**
  - `url()` and `alternates()` from routing;
  - `t()` and the `locale` global, with translation;
  - `csrf_field()` and `csrf_token()`, with security.

  Add your own through `ViewHelpers`.
- **Globals:** `view.globals`, and `csp_nonce` when
  `SecurityHeadersMiddleware` uses `{nonce}` ([HTTP](http.md)).
- **Error pages:** browsers get `templates/errors/<status>.php`, otherwise
  `templates/errors/error.php`, in the request's language. JSON clients
  keep problem documents.

## Twig

Register `TwigServiceProvider` as well. Templates then end in
`.html.twig`, in the same folders, with the same names:

```twig
{% extends 'layouts/site' %}
{% block content %}<a href="{{ url('contact') }}">{{ 'nav.contact'|t }}</a>{{ csrf_field('contact') }}{% endblock %}
```

- **Autoescape** is `html`, and every helper is a Twig function.
- **Cache:** in production, compiled templates are cached in `view.cache`.

## Rules worth knowing

- **Values passed on are printed as given.** Values a template passes to
  `layout()` or `insert()` are not escaped again, since they are already
  escaped. Give helpers `$this->raw('name')` when they need the original
  text.
- **Template names** never contain `..` or NUL bytes. The extension is
  optional.
- **The renderer is scoped.** It reads the current request, for the nonce.
  Resolve it from a scope, or inject it into scoped services and
  controllers.

Reference: the [view README](https://github.com/mjnable/zana-view).

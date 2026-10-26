# Translation

`mj-zana/translation` translates interface strings and knows the locale of
the current request: the URL prefix, then the browser's languages, then
the fallback.

```
composer require mj-zana/translation
```

## Locales

The first supported locale is the fallback. Negotiation follows RFC 4647:
ranges by quality, then by order. A region falls back to its language
(`fr-BE` gives `fr`).

<!-- example: examples/guides/translation.php#negotiate -->
```php
$locales = new Locales(['en', 'fr', 'fr-CA']);           // the first is the fallback

echo $locales->negotiate('fr-BE,fr;q=0.9,en;q=0.5'), "\n";   // fr-BE is not supported: fr
echo $locales->negotiate('fr-ca'), "\n";                      // matched case-insensitively
echo $locales->negotiate('de,es;q=0.8'), "\n";                // nothing supported: the fallback
```

## Translating

Catalogs are nested arrays, read with dotted keys. `{name}` placeholders
are replaced.

<!-- example: examples/guides/translation.php#translate -->
```php
$catalogs = (new Catalogs())
    ->add('en', ['nav' => ['home' => 'Home'], 'cart' => ['items' => 'You have {count} items']])
    ->add('fr', ['nav' => ['home' => 'Accueil']]);
$context = new LocaleContext($locales);
$translator = new Translator($catalogs, $context, $locales);

echo $translator->translate('nav.home'), "\n";                       // outside a request: the fallback
$context->run('fr', static function () use ($translator): void {     // LocaleMiddleware does this per request
    echo $translator->translate('nav.home'), "\n";
    echo $translator->translate('cart.items', ['count' => 3]), "\n"; // missing in fr: the fallback's text
});
echo $translator->translate('missing.key'), "\n";                    // missing everywhere: the key
```

<!-- example: examples/guides/translation.out -->
```text
fr
fr-CA
en
Home
Accueil
You have 3 items
missing.key
```

## In an application

Register `TranslationServiceProvider` and list the locales in
`config/translation.php`: `return ['locales' => ['en', 'fr']];`.

- **Catalogs:** each module's `Resources/translations/<locale>.php` loads in
  module order. The application's `translations/` folder loads last and wins.
- **The request locale:** add `LocaleMiddleware` to `http.middleware`. For
  the duration of the request it sets:
  - the `_locale` attribute and `LocaleContext`;
  - `Content-Language`, and `Vary: Accept-Language` when the browser's
    languages decided.
- **One URL per language:** use `Router::localized()` ([routing](routing.md)).
- **In templates:** `t()` and the `locale` global ([views](view.md)).

## Rules worth knowing

- **Missing keys never throw.** The fallback locale is tried, then the key
  itself is returned, with its placeholders replaced.
- **ICU messages** such as `{count, plural, one {# item} other {# items}}`
  are formatted when `ext-intl` is loaded. Without intl, placeholders are
  only replaced.
- **Validation messages** are translated when `mj-zana/validation` is
  installed:
  - give constraints keys as messages, such as
    `#[NotBlank(message: 'contact.error.name')]`;
  - translate the built-in English messages by their text.
- **Outside a request**, the locale is the fallback. Use
  `LocaleContext::run('fr', $callback)` in console commands and jobs.

## New in 2.2

- **A remembered choice.** With `translation.cookie` set, the language
  switcher's choice is kept in a cookie and wins over `Accept-Language`
  (but not over a locale in the URL). Point the switcher at
  `LocaleSwitchAction`:

  ```php
  $router->get('/locale/{locale}', LocaleSwitchAction::class)->name('locale.switch');
  // <a href="/locale/fr?to=/fr/a-propos">Français</a>
  ```

  `to` must be a path on the site.
- **The home page.** With `translation.redirect_root`, a request for `/`
  is redirected (302) to `/<locale>`.
- **Translated content.** `LocalizedValue` holds one value per locale for
  content stored in the database (see
  [translated content](../cookbook/recipes.md#translated-content)).

Reference: the [translation README](https://github.com/mjnable/zana-translation).

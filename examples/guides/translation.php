<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Translation\Catalogs;
use Zana\Translation\LocaleContext;
use Zana\Translation\Locales;
use Zana\Translation\Translator;

// region: negotiate
$locales = new Locales(['en', 'fr', 'fr-CA']);           // the first is the fallback

echo $locales->negotiate('fr-BE,fr;q=0.9,en;q=0.5'), "\n";   // fr-BE is not supported: fr
echo $locales->negotiate('fr-ca'), "\n";                      // matched case-insensitively
echo $locales->negotiate('de,es;q=0.8'), "\n";                // nothing supported: the fallback
// endregion: negotiate

// region: translate
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
// endregion: translate

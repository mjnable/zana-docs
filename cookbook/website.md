# A company website

Most first projects are not APIs but a company website: a few pages in
two languages, a contact form, good security headers, and a deployment
on modest hosting. This chapter builds one with Zana 2.1:

- **Pages and templates:** a layout, module templates, and error pages.
- **Languages:** French and English, the visitor's browser choosing the
  first one, and one URL per language.
- **A contact form:** validation with translated messages, CSRF
  protection, and an email to the company.
- **Security headers:** a Content-Security-Policy with a nonce, and safe
  defaults.
- **Deployment safety:** the site refuses to start when it would leak
  internals.
- **Tests** for pages and forms.

The complete application is in
[`examples/cookbook/website`](../examples/cookbook/website). Every listing
below comes from it, and its tests run with this documentation's test
suite. It follows the [module cookbook](modules.md): a `Site` module for
the pages and a `Contact` module for the form.

```
composer require mj-zana/http mj-zana/routing mj-zana/view mj-zana/translation \
    mj-zana/security mj-zana/validation mj-zana/communication
composer require --dev mj-zana/testing
```

## 1. The application

The bootstrap lists the packages' providers. Modules come from
`config/modules.php`.

<!-- example: examples/cookbook/website/src/Bootstrap.php -->
```php
<?php

declare(strict_types=1);

namespace Website;

use Zana\Communication\CommunicationServiceProvider;
use Zana\Core\Application;
use Zana\Core\ApplicationBuilder;
use Zana\Http\HttpServiceProvider;
use Zana\Routing\RoutingServiceProvider;
use Zana\Security\SecurityServiceProvider;
use Zana\Translation\TranslationServiceProvider;
use Zana\Validation\ValidationServiceProvider;
use Zana\View\ViewServiceProvider;

final class Bootstrap
{
    public static function builder(string $basePath): ApplicationBuilder
    {
        return Application::configure($basePath)
            ->withProviders(
                HttpServiceProvider::class,
                RoutingServiceProvider::class,
                TranslationServiceProvider::class,
                SecurityServiceProvider::class,
                ValidationServiceProvider::class,
                CommunicationServiceProvider::class,
                ViewServiceProvider::class,
            );
    }
}
```

<!-- example: examples/cookbook/website/config/modules.php -->
```php
<?php

return [
    Modules\Site\SiteModule::class,
    Modules\Contact\ContactModule::class,
];
```

The front controller builds and serves with `HttpKernel::run()`. If the
application cannot start, because of missing configuration, an invalid
container or a fatal deployment check, visitors get `public/503.html`
with status 503. The reason goes to PHP's error log and is never shown,
even with debug on.

<!-- example: examples/cookbook/website/public/index.php -->
```php
<?php

declare(strict_types=1);

use Website\Bootstrap;
use Zana\Http\HttpKernel;

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';

// If the application cannot start (missing configuration, a fatal deployment
// check), visitors get public/503.html and the reason goes to PHP's error log.
HttpKernel::run(
    Bootstrap::builder($base)
        ->withContainerCache($base . '/var/cache/container.php')
        ->withConfigurationCache($base . '/var/cache/config.php'),   // production only (2.4)
);
```

## 2. Configuration

Each file in `config/` is one section. Secrets and server-specific values
come from environment variables on the server: `APP_SECURITY__SECRET`,
`APP_MAIL__DSN` and `APP_APP__URL`.

<!-- example: examples/cookbook/website/config/app.php -->
```php
<?php

return [
    // The public address. With debug on, a public url refuses to start: set APP_APP__URL on the server.
    'url' => 'http://localhost:8000',
    'name' => 'Acme',
];
```

<!-- example: examples/cookbook/website/config/http.php -->
```php
<?php

use Zana\Http\SecurityHeadersMiddleware;
use Zana\Translation\LocaleMiddleware;

return [
    'middleware' => [SecurityHeadersMiddleware::class, LocaleMiddleware::class],
    'security_headers' => [
        'csp' => [
            'script-src' => ["'self'", '{nonce}'],
            'img-src' => ["'self'", 'data:'],
        ],
    ],
];
```

<!-- example: examples/cookbook/website/config/translation.php -->
```php
<?php

return ['locales' => ['en', 'fr']];   // the first is the fallback
```

<!-- example: examples/cookbook/website/config/security.php -->
```php
<?php

return [
    // A placeholder: production refuses to start until APP_SECURITY__SECRET replaces it.
    'secret' => 'change-me-to-64-random-hex-characters-generated-on-the-server',
];
```

<!-- example: examples/cookbook/website/config/mail.php -->
```php
<?php

return [
    'dsn' => 'memory://',                  // on the server: APP_MAIL__DSN=smtp://user:pass@mail.example.com:587
    'from' => ['address' => 'website@acme.example', 'name' => 'Acme website'],
];
```

<!-- example: examples/cookbook/website/config/contact.php -->
```php
<?php

return ['recipient' => 'hello@acme.example'];
```

- **Middleware order:** `SecurityHeadersMiddleware` comes first, so every
  response carries the headers, error pages included. `LocaleMiddleware`
  then picks the language: the URL prefix (`/fr/...`), then the browser's
  `Accept-Language`, then the first locale.
- **The CSP nonce:** `{nonce}` in the policy becomes a fresh nonce for
  each request. Templates print it as `$csp_nonce` on inline scripts.

## 3. Pages in two languages

`Router::localized()` registers one route per language, under its prefix,
and names the variants `about.en` and `about.fr`. Templates and controllers
use the base name `about`. The URL generator picks the current language,
and `alternates()` lists the page in every language.

<!-- example: examples/cookbook/website/modules/Site/SiteModule.php#routes -->
```php
$router->get('/', [PageController::class, 'root']);
$router->localized(['en' => '/', 'fr' => '/'], [PageController::class, 'home'])->name('home');
$router->localized(['en' => '/about', 'fr' => '/a-propos'], [PageController::class, 'about'])->name('about');
```

The controller returns `View` objects. The root, `/`, redirects to the
home page in the browser's language. This redirect depends on the
visitor, so it is a 302 with `Vary: Accept-Language`.

<!-- example: examples/cookbook/website/modules/Site/Presentation/Web/PageController.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Site\Presentation\Web;

use Psr\Http\Message\ResponseInterface;
use Zana\Http\ResponseFactory;
use Zana\Routing\UrlGenerator;
use Zana\Translation\LocaleContext;
use Zana\View\View;

final readonly class PageController
{
    public function __construct(private ResponseFactory $responses)
    {
    }

    /**
     * "/" sends visitors to the home page in their browser's language. The
     * redirect depends on Accept-Language, so it is never cached as permanent.
     */
    public function root(LocaleContext $locale, UrlGenerator $urls): ResponseInterface
    {
        return $this->responses->redirect($urls->generate('home', ['_locale' => $locale->get()]), 302)
            ->withHeader('Vary', 'Accept-Language');
    }

    public function home(): View
    {
        return View::make('@Site/home', ['page' => 'home']);
    }

    public function about(): View
    {
        return View::make('@Site/about', ['page' => 'about', 'founded' => 1998]);
    }
}
```

The module class registers the controller and adds the routes in
`boot()`:

<!-- example: examples/cookbook/website/modules/Site/SiteModule.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Site;

use Modules\Site\Presentation\Web\PageController;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\Module;
use Zana\Routing\Router;

final class SiteModule extends Module
{
    public function name(): string
    {
        return 'Site';
    }

    public function register(ServiceCollectionInterface $services): void
    {
        $services->transient(PageController::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $router = $container->get(Router::class);
        assert($router instanceof Router);

        $router->get('/', [PageController::class, 'root']);
        $router->localized(['en' => '/', 'fr' => '/'], [PageController::class, 'home'])->name('home');
        $router->localized(['en' => '/about', 'fr' => '/a-propos'], [PageController::class, 'about'])->name('about');
    }
}
```

## 4. Templates

Templates are plain PHP. Values from the controller are escaped before
the template sees them, so `<?= $title ?>` is safe. Helpers are called on
`$this`, and their output is escaped too:

- `t()` translates;
- `url()` and `alternates()` build links;
- `csrf_field()` prints the form token.

Each module's `Resources/views/` folder is available under the module's
name, such as `@Site/home`. The application's own templates live in
`templates/`.

<!-- example: examples/cookbook/website/templates/layouts/site.php -->
```php
<!doctype html>
<html lang="<?= $locale ?>">
<head>
  <meta charset="utf-8">
  <title><?= $title ?> | Acme</title>
<?php foreach (isset($page) ? $this->alternates($page) : [] as $language => $href): ?>
  <link rel="alternate" hreflang="<?= $language ?>" href="<?= $href ?>">
<?php endforeach ?>
</head>
<body>
  <nav>
    <a href="<?= $this->url('home') ?>"><?= $this->t('nav.home') ?></a>
    <a href="<?= $this->url('about') ?>"><?= $this->t('nav.about') ?></a>
    <a href="<?= $this->url('contact') ?>"><?= $this->t('nav.contact') ?></a>
<?php if (isset($page)): ?>
    <a class="language" href="<?= $this->url($page, ['_locale' => $locale === 'en' ? 'fr' : 'en']) ?>"><?= $this->t('nav.other_language') ?></a>
<?php endif ?>
  </nav>
  <main><?= $this->section('content') ?></main>
  <script nonce="<?= $csp_nonce ?>">document.documentElement.classList.add('js');</script>
</body>
</html>
```

<!-- example: examples/cookbook/website/modules/Site/Resources/views/home.php -->
```php
<?php $this->layout('layouts/site', ['title' => $this->t('home.title')]) ?>
<h1><?= $this->t('home.title') ?></h1>
<p><?= $this->t('home.intro') ?></p>
<p><a href="<?= $this->url('contact') ?>"><?= $this->t('home.cta') ?></a></p>
```

<!-- example: examples/cookbook/website/modules/Site/Resources/views/about.php -->
```php
<?php $this->layout('layouts/site', ['title' => $this->t('about.title')]) ?>
<h1><?= $this->t('about.title') ?></h1>
<p><?= $this->t('about.founded', ['year' => $founded]) ?></p>
```

The layout adds `hreflang` links and a language switcher for pages that
set `page`, the base route name.

## 5. Translations

Catalogs are PHP files named after the locale. Each module has its own,
in `Resources/translations/`. The application's catalogs, in
`translations/`, load last and can override any key.

<!-- example: examples/cookbook/website/modules/Site/Resources/translations/en.php -->
```php
<?php

return [
    'home' => ['title' => 'Welcome to Acme', 'intro' => 'Tools & services for small businesses.', 'cta' => 'Write to us'],
    'about' => ['title' => 'About us', 'founded' => 'Acme was founded in {year}.'],
];
```

<!-- example: examples/cookbook/website/modules/Site/Resources/translations/fr.php -->
```php
<?php

return [
    'home' => ['title' => 'Bienvenue chez Acme', 'intro' => 'Outils & services pour les petites entreprises.', 'cta' => 'Écrivez-nous'],
    'about' => ['title' => 'À propos', 'founded' => 'Acme a été fondée en {year}.'],
];
```

<!-- example: examples/cookbook/website/translations/en.php -->
```php
<?php

return [
    'nav' => ['home' => 'Home', 'about' => 'About', 'contact' => 'Contact', 'other_language' => 'Français'],
    'error' => [
        'title' => 'Error {status}',
        'not_found' => 'This page does not exist.',
        'other' => 'Something went wrong. Please try again later.',
    ],
];
```

<!-- example: examples/cookbook/website/translations/fr.php -->
```php
<?php

return [
    'nav' => ['home' => 'Accueil', 'about' => 'À propos', 'contact' => 'Contact', 'other_language' => 'English'],
    'error' => [
        'title' => 'Erreur {status}',
        'not_found' => 'Cette page n’existe pas.',
        'other' => 'Une erreur est survenue. Réessayez plus tard.',
    ],
];
```

## 6. Error pages

Browsers get `templates/errors/<status>.php`, otherwise
`templates/errors/error.php`, rendered in the request's language. JSON
clients still get problem documents. In debug mode, server errors keep the
built-in page with the stack trace.

<!-- example: examples/cookbook/website/templates/errors/error.php -->
```php
<?php $this->layout('layouts/site', ['title' => $this->t('error.title', ['status' => $status])]) ?>
<h1><?= $this->t('error.title', ['status' => $status]) ?></h1>
<p><?= $this->t($status === 404 ? 'error.not_found' : 'error.other') ?></p>
<p><a href="<?= $this->url('home') ?>"><?= $this->t('nav.home') ?></a></p>
```

## 7. The contact form

The message is a plain object with constraints. Its custom messages are
translation keys:

<!-- example: examples/cookbook/website/modules/Contact/Application/ContactMessage.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Contact\Application;

use Zana\Validation\Constraint\Email;
use Zana\Validation\Constraint\Length;
use Zana\Validation\Constraint\NotBlank;

/**
 * What a visitor sends. The messages are translation keys.
 */
final readonly class ContactMessage
{
    public function __construct(
        #[NotBlank(message: 'contact.error.name')]
        #[Length(max: 100)]
        public string $name,
        #[NotBlank(message: 'contact.error.email')]
        #[Email(message: 'contact.error.email')]
        public string $email,
        #[Length(min: 10, max: 5000, message: 'contact.error.message')]
        public string $message,
    ) {
    }
}
```

The application layer only knows that someone must be told. Sending email
is an infrastructure concern:

<!-- example: examples/cookbook/website/modules/Contact/Application/ContactNotifier.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Contact\Application;

/**
 * Tells the company about a message; the infrastructure decides how.
 */
interface ContactNotifier
{
    public function notify(ContactMessage $message): void;
}
```

<!-- example: examples/cookbook/website/modules/Contact/Infrastructure/MailContactNotifier.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Contact\Infrastructure;

use Modules\Contact\Application\ContactMessage;
use Modules\Contact\Application\ContactNotifier;
use Zana\Communication\Address;
use Zana\Communication\MailerInterface;
use Zana\Communication\Message;

final readonly class MailContactNotifier implements ContactNotifier
{
    public function __construct(
        private MailerInterface $mailer,
        private string $recipient,
    ) {
    }

    public function notify(ContactMessage $message): void
    {
        // From is the site's own address (mail.from); the visitor goes in Reply-To.
        $this->mailer->send(Message::create()
            ->to($this->recipient)
            ->replyTo(new Address($message->email, $message->name))
            ->subject("Website message from {$message->name}")
            ->text($message->message));
    }
}
```

The From address is always the site's own, `mail.from`, and the visitor
goes in Reply-To. This keeps SPF and DMARC aligned, and a visitor cannot
make the site send mail as someone else. The mailer refuses any other
From.

The controller shows the form, and validates and sends it. Invalid input
shows the form again with status 422 and the visitor's values. Success
redirects to a thank-you page (Post/Redirect/Get).

<!-- example: examples/cookbook/website/modules/Contact/Presentation/Web/ContactController.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Contact\Presentation\Web;

use Modules\Contact\Application\ContactMessage;
use Modules\Contact\Application\ContactNotifier;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zana\Http\ResponseFactory;
use Zana\Routing\UrlGenerator;
use Zana\Validation\Validator;
use Zana\View\View;

final readonly class ContactController
{
    public function __construct(
        private Validator $validator,
        private ContactNotifier $notifier,
        private UrlGenerator $urls,
        private ResponseFactory $responses,
    ) {
    }

    public function show(): View
    {
        return View::make('@Contact/form', ['page' => 'contact', 'old' => [], 'errors' => []]);
    }

    public function send(ServerRequestInterface $request): View|ResponseInterface
    {
        $body = $request->getParsedBody();
        $fields = [];
        foreach (['name', 'email', 'message'] as $field) {
            $value = is_array($body) ? ($body[$field] ?? '') : '';
            $fields[$field] = is_string($value) ? trim($value) : '';
        }

        $message = new ContactMessage($fields['name'], $fields['email'], $fields['message']);
        $violations = $this->validator->validate($message);
        if ($violations->hasViolations()) {
            // Translated in the request's locale by mj-zana/translation.
            return View::make('@Contact/form', ['page' => 'contact', 'old' => $fields, 'errors' => $violations->toArray()])->withStatus(422);
        }

        $this->notifier->notify($message);

        // Post/Redirect/Get, so reloading the thank-you page sends nothing twice.
        return $this->responses->redirect($this->urls->generate('contact.thanks'), 303);
    }

    public function thanks(): View
    {
        return View::make('@Contact/thanks', ['page' => 'contact.thanks']);
    }
}
```

The form posts are protected by `CsrfMiddleware`, added to the POST
routes only. The token lives in the form itself, so no session or cookie
is needed:

<!-- example: examples/cookbook/website/modules/Contact/ContactModule.php#routes -->
```php
$paths = ['en' => '/contact', 'fr' => '/contact'];
$router->localized($paths, [ContactController::class, 'show'])->name('contact');
$router->localized($paths, [ContactController::class, 'send'], ['POST'])->middleware(CsrfMiddleware::class);
$router->localized(['en' => '/contact/thanks', 'fr' => '/contact/merci'], [ContactController::class, 'thanks'])->name('contact.thanks');
```

<!-- example: examples/cookbook/website/modules/Contact/Resources/views/form.php -->
```php
<?php $this->layout('layouts/site', ['title' => $this->t('contact.title')]) ?>
<h1><?= $this->t('contact.title') ?></h1>
<form method="post" action="<?= $this->url('contact') ?>" novalidate>
  <?= $this->csrf_field('contact') ?>
<?php foreach (['name' => 'text', 'email' => 'email'] as $field => $type): ?>
  <label><?= $this->t("contact.{$field}") ?>
    <input type="<?= $type ?>" name="<?= $field ?>" value="<?= $old[$field] ?? '' ?>">
  </label>
<?php foreach ($errors[$field] ?? [] as $error): ?><p class="error"><?= $error ?></p><?php endforeach ?>
<?php endforeach ?>
  <label><?= $this->t('contact.message') ?>
    <textarea name="message"><?= $old['message'] ?? '' ?></textarea>
  </label>
<?php foreach ($errors['message'] ?? [] as $error): ?><p class="error"><?= $error ?></p><?php endforeach ?>
  <button><?= $this->t('contact.send') ?></button>
</form>
```

<!-- example: examples/cookbook/website/modules/Contact/Resources/views/thanks.php -->
```php
<?php $this->layout('layouts/site', ['title' => $this->t('contact.thanks')]) ?>
<h1><?= $this->t('contact.thanks') ?></h1>
```

<!-- example: examples/cookbook/website/modules/Contact/Resources/translations/en.php -->
```php
<?php

return ['contact' => [
    'title' => 'Write to us',
    'name' => 'Your name',
    'email' => 'Your email',
    'message' => 'Your message',
    'send' => 'Send',
    'thanks' => 'Thank you, we will reply soon.',
    'error' => [
        'name' => 'Please enter your name.',
        'email' => 'Please enter a valid email address.',
        'message' => 'Please write at least 10 characters.',
    ],
]];
```

<!-- example: examples/cookbook/website/modules/Contact/Resources/translations/fr.php -->
```php
<?php

return ['contact' => [
    'title' => 'Écrivez-nous',
    'name' => 'Votre nom',
    'email' => 'Votre e-mail',
    'message' => 'Votre message',
    'send' => 'Envoyer',
    'thanks' => 'Merci, nous vous répondrons bientôt.',
    'error' => [
        'name' => 'Indiquez votre nom.',
        'email' => 'Indiquez une adresse e-mail valide.',
        'message' => 'Écrivez au moins 10 caractères.',
    ],
]];
```

The complete module class:

<!-- example: examples/cookbook/website/modules/Contact/ContactModule.php -->
```php
<?php

declare(strict_types=1);

namespace Modules\Contact;

use Modules\Contact\Application\ContactNotifier;
use Modules\Contact\Infrastructure\MailContactNotifier;
use Modules\Contact\Presentation\Web\ContactController;
use Zana\Communication\MailerInterface;
use Zana\Configuration\ConfigurationInterface;
use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Container\Contracts\ServiceResolverInterface;
use Zana\Core\Module;
use Zana\Routing\Router;
use Zana\Security\Middleware\CsrfMiddleware;

final class ContactModule extends Module
{
    public function name(): string
    {
        return 'Contact';
    }

    public function dependsOn(): array
    {
        return [\Modules\Site\SiteModule::class];   // its pages use the site layout
    }

    public function register(ServiceCollectionInterface $services): void
    {
        $services
            ->singleton(ContactNotifier::class, static fn (ServiceResolverInterface $r): ContactNotifier => new MailContactNotifier(
                $r->get(MailerInterface::class),
                $r->get(ConfigurationInterface::class)->string('contact.recipient'),
            ))
            ->transient(ContactController::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $router = $container->get(Router::class);
        assert($router instanceof Router);

        $paths = ['en' => '/contact', 'fr' => '/contact'];
        $router->localized($paths, [ContactController::class, 'show'])->name('contact');
        $router->localized($paths, [ContactController::class, 'send'], ['POST'])->middleware(CsrfMiddleware::class);
        $router->localized(['en' => '/contact/thanks', 'fr' => '/contact/merci'], [ContactController::class, 'thanks'])->name('contact.thanks');
    }
}
```

## 8. Testing pages and forms

The test case builds the application from the same bootstrap, in the
`testing` environment.

- **No leaks from `.env`:** `.env.testing` replaces a developer's `.env`
  when it exists.
- **Mail is recorded:** `mail.dsn` is `memory://`, so messages are kept,
  not sent.

<!-- example: examples/cookbook/website/tests/WebsiteTest.php#setup -->
```php
protected function createApplicationBuilder(): ApplicationBuilder
{
    return Bootstrap::builder(dirname(__DIR__))
        ->withEnvironment('testing')
        ->withConfiguration(['security.secret' => str_repeat('t', 64)]);
}
```

`browser()` asks for HTML, as a browser does. `assertSee()` compares
escaped text, and `assertSelector()` takes CSS selectors.

<!-- example: examples/cookbook/website/tests/WebsiteTest.php#pages -->
```php
public function testTheRootFollowsTheBrowserLanguage(): void
{
    $this->browser()->get('/', headers: ['Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.5'])
        ->assertRedirect('/fr')
        ->assertHeader('Vary', 'Accept-Language');
}

public function testPagesExistInEveryLanguage(): void
{
    $this->browser()->get('/en/about')->assertOk()->assertSelector('h1', 'About us')->assertSee('founded in 1998');
    $this->browser()->get('/fr/a-propos')
        ->assertOk()
        ->assertHeader('Content-Language', 'fr')
        ->assertSelector('h1', 'À propos')
        ->assertSelector('link[rel=alternate][hreflang=en][href="/en/about"]')
        ->assertSelector('a.language[href="/en/about"]', 'English');
}

public function testEveryResponseCarriesSecurityHeaders(): void
{
    $page = $this->browser()->get('/en');

    $page->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
    $this->assertSame(1, preg_match("/script-src 'self' 'nonce-([^']+)'/", $page->header('Content-Security-Policy'), $match));
    $page->assertSee('<script nonce="' . ($match[1] ?? '') . '">', escape: false);
}

public function testMissingPagesAreBrandedAndTranslated(): void
{
    $this->browser()->get('/fr/nulle-part')->assertStatus(404)->assertSelector('h1', 'Erreur 404')->assertSee('Cette page n’existe pas.');
}
```

`form()` loads the page and reads its form, hidden fields included, so
the CSRF token is posted back exactly as a browser would:

<!-- example: examples/cookbook/website/tests/WebsiteTest.php#contact -->
```php
public function testTheContactFormSendsAnEmail(): void
{
    $this->browser()->form('/en/contact')
        ->fill(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'message' => 'I would like a quote.'])
        ->submit()
        ->assertRedirect('/en/contact/thanks');

    [$mail] = $this->assertMailSent(static fn (Message $m): bool => $m->subject === 'Website message from Ada Lovelace');
    $this->assertSame('website@acme.example', $mail->from?->email);
    $this->assertSame('ada@example.com', $mail->replyTo[0]->email);
    $this->assertSame('hello@acme.example', $mail->to[0]->email);
}

public function testInvalidMessagesAreShownAgainWithTranslatedErrors(): void
{
    $this->browser()->form('/fr/contact')
        ->fill(['name' => '', 'email' => 'not-an-email', 'message' => 'Hi'])
        ->submit()
        ->assertStatus(422)
        ->assertSelector('p.error', 'Indiquez votre nom.')
        ->assertSelector('p.error', 'Indiquez une adresse e-mail valide.')
        ->assertSelector('input[name=email][value=not-an-email]');

    $this->assertNoMailSent();
}

public function testPostsWithoutTheFormTokenAreRefused(): void
{
    $this->browser()->submit('/en/contact', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy cheap things now'])->assertForbidden();
    $this->assertNoMailSent();
}
```

## 9. Going live

The application checks its own deployment when it starts. With debug on,
a public `app.url` refuses to start, because visitors would see stack
traces. In production, so does a placeholder secret. `withConfig()`
changes one setting for one test:

<!-- example: examples/cookbook/website/tests/WebsiteTest.php#deployment -->
```php
public function testDebugOnAPublicAddressRefusesToStart(): void
{
    $this->expectException(UnsafeDeploymentException::class);
    $this->withConfig(['app.url' => 'https://www.acme.com']);   // the testing environment has debug on
}
```

On the server:

1. **Environment:** set `APP_ENV=production`, and `APP_APP__URL`,
   `APP_SECURITY__SECRET` (64 random hexadecimal characters) and
   `APP_MAIL__DSN`.
2. **Document root:** point it at `public/`. If the hosting fixes the
   document root, upload only `public/` there and keep the rest of the
   project outside it.
3. **Cache directory:** make `var/` writable. The compiled container is
   cached in `var/cache/container.php`. Delete it after each deployment, as
   a stale container is an error in production.
4. **Fallback page:** keep `public/503.html` small and static. It is all
   visitors see when the application cannot start.

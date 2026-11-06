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
use Zana\Security\Middleware\RateLimitMiddleware;

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

        // region: routes
        $paths = ['en' => '/contact', 'fr' => '/contact'];
        $router->localized($paths, [ContactController::class, 'show'])->name('contact');
        // The rate limit counts every post, before the bot traps and the token are checked.
        $router->localized($paths, [ContactController::class, 'send'], ['POST'])->middleware(RateLimitMiddleware::class, CsrfMiddleware::class);
        $router->localized(['en' => '/contact/thanks', 'fr' => '/contact/merci'], [ContactController::class, 'thanks'])->name('contact.thanks');
        // endregion: routes
    }
}

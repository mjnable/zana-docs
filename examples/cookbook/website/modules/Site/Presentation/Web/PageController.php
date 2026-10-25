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

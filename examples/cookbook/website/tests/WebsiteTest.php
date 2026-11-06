<?php

declare(strict_types=1);

namespace Website\Tests;

use Website\Bootstrap;
use Zana\Communication\Message;
use Zana\Core\ApplicationBuilder;
use Zana\Core\Deployment\UnsafeDeploymentException;
use Zana\Testing\ApplicationTestCase;

final class WebsiteTest extends ApplicationTestCase
{
    // region: setup
    protected function createApplicationBuilder(): ApplicationBuilder
    {
        return Bootstrap::builder(dirname(__DIR__))
            ->withEnvironment('testing')
            ->withConfiguration([
                'security.secret' => str_repeat('t', 64),
                'security.csrf.minimum_age' => 0,                     // tests fill forms faster than people
                'cache.stores.array' => ['driver' => 'array'],        // rate limits counted per test
                'cache.default' => 'array',
            ]);
    }
    // endregion: setup

    // region: pages
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
    // endregion: pages

    // region: contact
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

    public function testAMailFailureKeepsTheMessageAndSaysSo(): void
    {
        $this->withConfig(['mail.dsn' => 'smtp://127.0.0.1:1']);   // a mail server that cannot be reached

        $this->browser()->form('/fr/contact')
            ->fill(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'message' => 'I would like a quote.'])
            ->submit()
            ->assertStatus(503)
            ->assertSelector('p.error[role=alert]', 'Votre message n’a pas pu être envoyé.')
            ->assertSelector('textarea[name=message]', 'I would like a quote.');
    }
    // endregion: contact

    // region: bots
    public function testBotsThatFillTheHoneypotAreToldItWorked(): void
    {
        $this->browser()->form('/en/contact')
            ->fill(['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Cheap watches', 'website' => 'https://spam.example'])
            ->submit()
            ->assertRedirect('/en/contact/thanks');

        $this->assertNoMailSent();
    }

    public function testFormsSentFasterThanAPersonCouldAreIgnored(): void
    {
        $this->withConfig(['security.csrf.minimum_age' => 3]);   // as in production

        $this->browser()->form('/en/contact')
            ->fill(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'message' => 'I would like a quote.'])
            ->submit()
            ->assertRedirect('/en/contact/thanks');

        $this->assertNoMailSent();
    }

    public function testTheSixthMessageInAnHourIsRefused(): void
    {
        $send = fn () => $this->browser()->form('/en/contact')
            ->fill(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'message' => 'I would like a quote.'])
            ->submit();

        foreach (range(1, 5) as $_) {
            $send()->assertRedirect('/en/contact/thanks');
        }
        $send()->assertStatus(429)->assertHeader('RateLimit-Remaining', '0');

        $this->assertMailSent(times: 5);
    }
    // endregion: bots

    // region: deployment
    public function testDebugOnAPublicAddressRefusesToStart(): void
    {
        $this->expectException(UnsafeDeploymentException::class);
        $this->withConfig(['app.url' => 'https://www.acme.com']);   // the testing environment has debug on
    }
    // endregion: deployment
}

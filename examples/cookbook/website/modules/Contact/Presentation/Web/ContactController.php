<?php

declare(strict_types=1);

namespace Modules\Contact\Presentation\Web;

use Modules\Contact\Application\ContactMessage;
use Modules\Contact\Application\ContactNotifier;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Zana\Communication\MailException;
use Zana\Http\ResponseFactory;
use Zana\Routing\UrlGenerator;
use Zana\Security\Attribute\RateLimit;
use Zana\Validation\Validator;
use Zana\View\View;

final readonly class ContactController
{
    public function __construct(
        private Validator $validator,
        private ContactNotifier $notifier,
        private UrlGenerator $urls,
        private ResponseFactory $responses,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function show(): View
    {
        return View::make('@Contact/form', ['page' => 'contact', 'old' => [], 'errors' => []]);
    }

    // region: rate-limit
    #[RateLimit(max: 5, per: 3600)]   // five messages per visitor per hour; the sixth gets 429
    // endregion: rate-limit
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

        // region: mail-failure
        try {
            $this->notifier->notify($message);
        } catch (MailException $e) {
            // The mail server refused or could not be reached: keep what the visitor wrote,
            // say so, and leave the reason in the log (php bin/console mail:test shows it too).
            $this->logger?->error('Contact message from {email} not sent: {reason}', ['email' => $message->email, 'reason' => $e->getMessage(), 'exception' => $e]);

            return View::make('@Contact/form', ['page' => 'contact', 'old' => $fields, 'errors' => [], 'notSent' => true])->withStatus(503);
        }
        // endregion: mail-failure

        // Post/Redirect/Get, so reloading the thank-you page sends nothing twice.
        return $this->responses->redirect($this->urls->generate('contact.thanks'), 303);
    }

    public function thanks(): View
    {
        return View::make('@Contact/thanks', ['page' => 'contact.thanks']);
    }
}

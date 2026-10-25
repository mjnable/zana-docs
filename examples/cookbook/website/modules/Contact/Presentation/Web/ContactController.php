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

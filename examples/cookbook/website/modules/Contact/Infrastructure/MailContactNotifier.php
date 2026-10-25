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

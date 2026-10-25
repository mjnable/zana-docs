<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Communication\Address;
use Zana\Communication\MailException;
use Zana\Communication\MailRecorder;
use Zana\Communication\MailRules;
use Zana\Communication\Message;

// region: send
// CommunicationServiceProvider builds this from mail.*; with mail.dsn "memory://" the mailer is a recorder.
$mailer = new MailRecorder(new MailRules(new Address('website@example.com', 'Example')));

$mailer->send(Message::create()
    ->to('contact@example.com')
    ->replyTo(new Address('ada@visitor.example', 'Ada'))   // the visitor: Reply-To, never From
    ->subject('New message from the website')
    ->text('Hello!'));

$sent = $mailer->last();
echo 'From: ', $sent?->from, "\n";
echo 'Reply-To: ', $sent?->replyTo[0] ?? '', "\n";
// endregion: send

// region: rules
foreach ([
    static fn () => $mailer->send(Message::create()->from('ada@visitor.example')->to('contact@example.com')->subject('Hi')->text('x')),
    static fn () => Message::create()->subject("Hi\r\nBcc: everyone@example.com"),
    static fn () => $mailer->send(Message::create()->to('contact@example.com')->text('No subject')),
] as $attempt) {
    try {
        $attempt();
    } catch (MailException $e) {
        echo $e->getMessage(), "\n";
    }
}
// endregion: rules

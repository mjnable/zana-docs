# Communication: email

`mj-zana/communication` sends email through Symfony Mailer transports,
with safe sender rules and a recorder for tests.

```
composer require mj-zana/communication
```

## Sending

Messages are immutable. The From address comes from configuration, and a
visitor's address goes in Reply-To:

<!-- example: examples/guides/communication.php#send -->
```php
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
```

## The rules

The mailer refuses messages that would spoof a sender, inject headers, or
miss a part:

<!-- example: examples/guides/communication.php#rules -->
```php
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
```

<!-- example: examples/guides/communication.out -->
```text
From: Example <website@example.com>
Reply-To: Ada <ada@visitor.example>
The application may not send from ada@visitor.example; use mail.from or list it in mail.allowed_from, and put visitors' addresses in Reply-To.
The subject cannot contain line breaks.
A message needs a subject.
```

## In an application

Register `CommunicationServiceProvider` and configure `config/mail.php`:

```php
return [
    'dsn' => 'smtp://user:pass@mail.example.com:587',   // memory:// records instead (the default)
    'from' => ['address' => 'website@example.com', 'name' => 'Example'],
    'allowed_from' => ['newsletter@example.com'],      // other senders the application may use
    'reply_to' => 'contact@example.com',               // optional default
];
```

- **Inject `MailerInterface`** wherever mail is sent. In the application
  layer, prefer your own port, such as a `ContactNotifier` interface, and
  implement it with the mailer ([the website cookbook](../cookbook/website.md)).
- **Transports:** any Symfony Mailer DSN works: `smtp://`, `sendmail://` or
  `null://`. Keep the DSN in the environment: `APP_MAIL__DSN`.
- **Tests:** with `mail.dsn: memory://`, the mailer is a `MailRecorder`.
  `mj-zana/testing` adds `assertMailSent()` and `assertNoMailSent()`
  ([testing](testing.md)).

## Rules worth knowing

- **From is never user input.** A From other than `mail.from` or
  `mail.allowed_from` is refused. This keeps SPF and DMARC aligned, and no
  one can send as someone else.
- **Required:** at least one recipient, a subject, and a text or HTML body.
- **Line breaks** in a subject, a header or an address name are refused.
- **Transport failures** throw `MailException`, with the transport's error
  as the previous exception.
- **Bodies:** for HTML email, render it with the view package and pass the
  string to `html()`.

Reference: the [communication README](https://github.com/mjnable/zana-communication).

# Security

`mj-zana/security` provides password hashing, signed bearer tokens,
authentication middleware, and authorization by roles, permissions and
policies.

```
composer require mj-zana/security
```

## Passwords

<!-- example: examples/guides/security.php#passwords -->
```php
$hasher = new PasswordHasher();                  // Argon2id when available, otherwise bcrypt
$hash = $hasher->hash('correct horse battery staple');
var_export($hasher->verify('correct horse battery staple', $hash));
echo "\n";
var_export($hasher->verify('wrong', $hash));
echo "\n";
```

After a successful `verify()`, check `needsRehash($hash)` and store a fresh
hash when the algorithm or its options have changed. Passwords longer than
4,096 bytes are refused.

bcrypt alone reads only the first 72 bytes of a password. For bcrypt, the
hasher first hashes longer passwords with SHA-384, so every byte still
counts.

**Not included in 2.0:** CSRF protection, CORS and security headers. Add
them as PSR-15 middleware, along with rate limiting for login attempts.

## Tokens

`TokenSigner` issues compact tokens made of a base64url JSON payload and an
HMAC-SHA256 signature. They are signed, not encrypted, so keep secrets out
of the claims.

<!-- example: examples/guides/security.php#tokens -->
```php
$now = 1_800_000_000;
$signer = new TokenSigner(str_repeat('s', 32), static function () use (&$now): int {
    return $now;
});

$token = $signer->issue('ada', ttlSeconds: 3600, claims: ['clinic' => 'north']);
$claims = $signer->verify($token);
echo $claims->subject, ' / ', $claims->claim('clinic'), "\n";

$now += 3600;
try {
    $signer->verify($token);
} catch (InvalidTokenException $e) {
    echo $e->reason, ': ', $e->getMessage(), "\n";
}
```

The secret must be at least 32 bytes. `InvalidTokenException::$reason` is
`malformed`, `signature` or `expired`.

## Authorization

`Authorizer::can()` asks the policies first; the first `true` or `false`
wins. When every policy abstains, the user's roles decide, inherited roles
included, and `*` grants everything.

<!-- example: examples/guides/security.php#authorization -->
```php
final class NotePolicy implements PolicyInterface
{
    public function supports(string $permission, ?object $subject): bool
    {
        return $permission === 'notes.edit' && $subject instanceof Note;
    }

    public function decide(UserInterface $user, string $permission, ?object $subject): ?bool
    {
        assert($subject instanceof Note);

        return $subject->authorId === $user->identifier() ? true : null;   // null: let the roles decide
    }
}

$authorizer = new Authorizer(
    roles: ['viewer' => ['notes.view'], 'editor' => ['notes.edit'], 'admin' => ['*']],
    hierarchy: ['editor' => ['viewer']],
    policies: [new NotePolicy()],
);

$grace = new User('grace', ['viewer']);
var_export($authorizer->can($grace, 'notes.view'));                      // from her role
echo "\n";
var_export($authorizer->can($grace, 'notes.edit', new Note('grace')));   // the policy: her own note
echo "\n";
var_export($authorizer->can($grace, 'notes.edit', new Note('ada')));     // the policy abstains, roles refuse
echo "\n";
var_export($authorizer->can(new User('ed', ['editor']), 'notes.view'));  // inherited from viewer
echo "\n";
```

The whole example prints:

<!-- example: examples/guides/security.out -->
```text
true
false
ada / north
expired: The token has expired.
true
true
false
true
```

## In an application

Register `SecurityServiceProvider`, implement `UserProviderInterface` (and
`UserInterface` for your users), and configure `config/security.php`:

```php
return [
    // 'secret': set APP_SECURITY__SECRET on the server; required once the signer is used
    'roles' => ['viewer' => ['notes.view'], 'admin' => ['*']],
    'hierarchy' => ['editor' => ['viewer']],
    'policies' => [NotePolicy::class],
];
```

- **`AuthenticationMiddleware`:** add it to `http.middleware`.
  - **Valid token:** for `Authorization: Bearer <token>`, it loads the user
    and sets them on the scoped `SecurityContext`, and as the request
    attribute `user`.
  - **No header:** the request continues anonymously.
  - **Anything wrong:** the request gets a 401 with `WWW-Authenticate:
    Bearer`.
- **`AuthorizationMiddleware`:** add it as *route* middleware (on a route or
  a group), because it reads the matched handler.
  - It enforces `#[RequiresAuthentication]` and `#[RequiresPermission('…')]`
    on controller classes, methods and closures. Permission attributes are
    repeatable, and every one is required.
  - A missing user gets a 401, a missing permission a 403.
- **In code:** `SecurityContext::requireUser()` and
  `Authorizer::authorize()` throw the same 401 and 403 errors.

## New in 2.1: CSRF protection

Forms are protected without sessions or cookies. `CsrfTokens` signs a
token per form with the `TokenSigner`, and `CsrfMiddleware` checks
unsafe methods:

1. a cross-site `Sec-Fetch-Site` or `Origin` header is refused;
2. the `_token` field (or the `X-CSRF-Token` header) must be valid for
   the `_form` field's form.

```php
$router->localized($paths, [ContactController::class, 'send'], ['POST'])->middleware(CsrfMiddleware::class);
```

In templates, `csrf_field('contact')` prints both hidden fields
([views](view.md)).

- **Exemptions:** requests with an `Authorization` header, and handlers
  marked `#[WithoutCsrf]`, are not checked.
- **Lifetime:** tokens expire after `security.csrf.lifetime` seconds
  (7,200 by default).
- **Single use:** with a PSR-16 cache, `security.csrf.single_use` makes
  each token work once.

## New in 2.2

### Rate limiting

```php
#[RateLimit(max: 5, per: 3600)]   // five contact messages per visitor per hour
public function send(ServerRequestInterface $request): ResponseInterface
```

Add `RateLimitMiddleware` as route middleware. Clients are counted by
address (`by: 'ip'`, the client address behind
[trusted proxies](http.md#behind-a-proxy)) or by user (`by: 'user'`), with a
sliding window by default (`strategy: 'fixed'` is cheaper). `name:` shares
one counter between handlers. Refused requests get a 429 with `Retry-After`,
and limited responses carry `RateLimit-Limit`, `-Remaining` and `-Reset`.
It needs a PSR-16 cache. `RateLimiter` can be used directly too.

### Spam on public forms

Two settings stop most bots without a CAPTCHA:

```php
'csrf' => [
    'honeypot' => 'website',     // a hidden field people never fill in
    'minimum_age' => 3,          // seconds: no person fills a form faster
    'spam_redirect' => '/thanks',
],
```

`csrf_field()` prints the honeypot. A filled honeypot or a form sent too
fast is refused, or, with `spam_redirect`, sent to that page so the bot
believes it succeeded.

### Signing in with a session

For a back office, `SessionLogin` signs users in with a password and keeps
them in the [session](session.md); `SessionAuthenticationMiddleware` loads
the user on later requests.

```php
$user = $login->attempt($input->string('email'), $input->string('password'), $context->clientIp());
```

- The users come from a `PasswordUserProviderInterface` (`findByLogin()`,
  `passwordHashFor()`). With `PasswordUpgraderInterface`, old hashes are
  rehashed at login.
- A failed attempt never reveals whether the account exists.
- Attempts are limited per login and per address
  (`security.login.max_attempts`, default 5, per `security.login.window`,
  default 900 seconds).
- The session gets a new id at login.
- An HTML request that needs a user is redirected to
  `security.login.path` with `?to=`; JSON requests get a 401.
- `PasswordResetTokens` issues signed, single-use reset tokens. It is
  scoped (2.4), like `SessionLogin`, so the user provider may be a singleton
  or scoped, like other repositories. A singleton of yours cannot keep it;
  the build says so.

The [back office chapter](../cookbook/back-office.md) puts it together.

Reference: the [security README](https://github.com/mjnable/zana-security).

## New in 2.15: security events

With [events](events.md) installed, `SessionLogin` dispatches
`SignInSucceeded`, `SignInFailed` and `SignedOut`. `SignInFailed` has the
reason `invalid_credentials` or `throttled`. `PasswordResetTokens` dispatches
`PasswordResetRequested` and `PasswordResetTokenUsed`. All of them implement
`SecurityEvent`.

[audit](audit.md#new-in-215-the-security-audit-log) records them in its
security audit log. You can also listen to them yourself, for example to
alert on repeated failures:

```php
$listeners->listen(SignInFailed::class, AlertOnRepeatedFailures::class);
```

A failed sign-in carries the login that was typed and the client address,
never the password. Pass the client address to `logout()` as well, so the
`SignedOut` event has it.

## New in 2.16: second factors

Organizer staff who handle money sign in with a code from an authenticator
app after their password. Your user provider stores one sealed string per
user, in a nullable text column:

<!-- example: examples/guides/two-factor.php#provider -->
```php
// Your user repository: one nullable text column, two_factor, holds the sealed state.
final class StaffUsers implements PasswordUserProviderInterface, TwoFactorUserProviderInterface
{
    /** @var array<string, array{user: Staff, hash: string, two_factor: ?string}> */
    public array $rows = [];

    public function findByIdentifier(string $identifier): ?UserInterface
    {
        return $this->rows[$identifier]['user'] ?? null;
    }

    public function findByLogin(string $login): ?UserInterface
    {
        foreach ($this->rows as $row) {
            if ($row['user']->email === $login) {
                return $row['user'];
            }
        }

        return null;
    }

    public function passwordHashFor(UserInterface $user): ?string
    {
        return $this->rows[$user->identifier()]['hash'] ?? null;
    }

    public function twoFactorStateFor(UserInterface $user): ?string
    {
        return $this->rows[$user->identifier()]['two_factor'] ?? null;
    }

    public function saveTwoFactorState(UserInterface $user, ?string $state): void
    {
        $this->rows[$user->identifier()]['two_factor'] = $state;
    }
}
```

Set `security.two_factor.key` from the environment
(`APP_SECURITY__TWO_FACTOR__KEY`, at least 32 characters, e.g. from
`php -r "echo bin2hex(random_bytes(32));"`). It is separate from
`security.secret`, and changing it locks out every user with a second
factor. `check` reports it when it is missing.

A user enrols by scanning the `otpauth://` URI (draw it as a QR code) and
typing a first code:

<!-- example: examples/guides/two-factor.php#enrol -->
```php
$enrolment = $twoFactor->enrol($amani, $amani->email);
echo 'Scan: ', substr($enrolment->uri, 0, 40), "...\n";        // show it as a QR code
echo 'Recovery codes: ', count($enrolment->recoveryCodes), "\n";
echo 'Confirmed: ', $twoFactor->confirm($amani, $app($enrolment->secret)) ? 'yes' : 'no', "\n";
```

From then on, `SessionLogin` asks for a code after the password:

<!-- example: examples/guides/two-factor.php#sign-in -->
```php
$result = $login->start('amani@example.com', 'correct horse', '198.51.100.7');
echo 'After the password: ', $result->status, ', signed in as ', $context->user()?->identifier() ?? 'nobody', "\n";

$result = $login->completeSecondFactor('123456', '198.51.100.7');
echo 'A wrong code: ', $result->status, "\n";

$result = $login->completeSecondFactor($app($enrolment->secret), '198.51.100.7', remember: true);
echo 'The code from the app: ', $result->status, ', signed in as ', $context->user()?->identifier() ?? 'nobody', "\n";
echo 'Device token for a cookie: ', $result->deviceToken === null ? 'no' : 'yes', "\n";
```

<!-- example: examples/guides/two-factor.out -->
```text
Scan: otpauth://totp/Billets%20RDC:amani%40exa...
Recovery codes: 10
Confirmed: yes
After the password: second_factor_required, signed in as nobody
A wrong code: failed
The code from the app: signed_in, signed in as 7
Device token for a cookie: yes
```

- **The password alone never signs in** a user with a second factor, not
  even through `attempt()`, which then returns null while
  `hasPendingSecondFactor()` is true. The pending sign-in lasts 5 minutes.
- **Codes** are accepted 30 seconds early or late, and only once. Attempts
  are limited per user.
- **Recovery codes** (10) are stored as HMACs. Each works once in place of a
  code, and `regenerateRecoveryCodes()` replaces them.
- **Remember this device:** put `$result->deviceToken` in a cookie and pass
  it to `start()`. It expires after `security.two_factor.remember_days`
  (30), and enrolling again forgets every device.
- **Events:** `TwoFactorEnabled`, `TwoFactorDisabled`, `TwoFactorFailed` and
  `RecoveryCodeUsed`. [audit](audit.md#new-in-215-the-security-audit-log)
  records them.

### One-time codes

To confirm a phone number or an email address, issue a code and send it
yourself, by SMS or WhatsApp:

```php
$code = $codes->issue('+243810000000', 'phone.confirm');   // OneTimeCodes
$codes->verify('+243810000000', 'phone.confirm', $typed);  // true once
```

A code expires (10 minutes by default) and works once. It is refused after 5
wrong tries, and issuing is limited per number and purpose
(`security.one_time_codes.max_issues` per `security.one_time_codes.window`).

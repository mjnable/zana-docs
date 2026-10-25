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
    'secret' => $_ENV['APP_SECRET'],                  // required once the signer is used
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

Reference: the [security README](https://github.com/mjnable/zana-security).

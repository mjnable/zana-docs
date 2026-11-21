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

- **Exemptions:** requests with a valid bearer token signed by the
  application, and handlers marked `#[WithoutCsrf]`, are not checked.
  Before 2.19.1, any `Authorization` header was enough.
- **The port counts (2.20):** an `Origin` that names a port must name the
  application's, so `https://example.com:8443`, another service on the same
  host, is another site. Browsers leave default ports out of `Origin`, so a
  site behind a proxy on 443 is not affected.
- **Lifetime:** tokens expire after `security.csrf.lifetime` seconds
  (7,200 by default).
- **Single use:** with a PSR-16 cache, `security.csrf.single_use` makes
  each token work once.
- **Tied to the session (2.28, opt-in):** `security.csrf.bind_to_session`;
  see [below](#new-in-228-lockouts-known-devices-and-tokens-tied-to-a-session).

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
It needs a PSR-16 cache. `RateLimiter` can be used directly too. Since 2.26
the limit holds for requests at the same instant on a Zana cache; see
[below](#new-in-226-limits-that-hold-under-concurrent-requests).

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
believes it succeeded. For what was sent, such as links and repeated
text, see [spam rules](#new-in-224-spam-rules) (2.24).

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
  default 900 seconds). Since 2.28 a guesser locks out only their own
  address; see [lockouts](#new-in-228-lockouts-known-devices-and-tokens-tied-to-a-session).
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
factor until it is set back. `check` reports it when it is missing.
Before 2.19.1, a changed key, or a damaged state, let the password alone
sign in: upgrade if you use second factors.

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
- **Two sign-ins at the same moment (2.20):** both read the stored state,
  and the one that saves last wins, so a recovery code the other used comes
  back, and one TOTP code can count twice. Implement
  `TwoFactorStateSwapInterface` to close this: `swapTwoFactorState()`
  stores the new state only if the stored one is still the one that was
  read, and the losing sign-in's code is refused, so the person types the
  next one.

  ```php
  final class StaffUsers implements PasswordUserProviderInterface, TwoFactorStateSwapInterface
  {
      // … the methods above, and:
      public function swapTwoFactorState(UserInterface $user, ?string $expected, ?string $new): bool
      {
          $query = $this->db->table('staff')->where('id', $user->identifier());
          $expected === null ? $query->whereNull('two_factor') : $query->where('two_factor', $expected);

          return $query->update(['two_factor' => $new]) === 1;
      }
  }
  ```

  Without it, second factors work as before, with that race.

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

## New in 2.17: roles in an organization or at an event

A user can be the owner of one organization, a box-office agent at another,
and a scanner at one event only. Turn on the store, and create its table
with a migration that returns `new RoleAssignmentsMigration()`:

```php
// config/security.php
'role_assignments' => 'database',   // with mj-zana/database; or register your own RoleAssignmentStoreInterface
```

When scopes nest, say so with a `ScopeHierarchyInterface`, registered in the
container. Subjects say which scope they belong to:

<!-- example: examples/guides/scoped-roles.php#hierarchy -->
```php
// Events belong to organizations: an organization's roles count at its events.
final class EventsInOrganizations implements ScopeHierarchyInterface
{
    /** @param array<string, string> $organizationOf event id => organization id */
    public function __construct(private array $organizationOf)
    {
    }

    public function parentsOf(Scope $scope): array
    {
        $organization = $scope->type === 'event' ? ($this->organizationOf[$scope->id] ?? null) : null;

        return $organization === null ? [] : [new Scope('organization', $organization)];
    }
}

final readonly class ConcertEvent implements HasAuthorizationScope
{
    public function __construct(public string $id)
    {
    }

    public function authorizationScope(): Scope
    {
        return new Scope('event', $this->id);
    }
}
```

Give roles with `RoleAssignments`, everywhere or in a scope. The
`Authorizer` then counts the roles held in the subject's scope and in the
scopes above it:

<!-- example: examples/guides/scoped-roles.php#assign -->
```php
$roles = new RoleAssignments($store);
$roles->assign('amani', 'owner', new Scope('organization', 'org-1'));
$roles->assign('bisimwa', 'scanner', new Scope('event', 'rumba-goma'));

$can = static fn (string $user, string $permission, object $subject): string => $authorizer->can(new Member($user), $permission, $subject) ? 'yes' : 'no';
echo 'Amani publishes Jazz Kinshasa (org-1): ', $can('amani', 'events.publish', new ConcertEvent('jazz-kin')), "\n";
echo 'Amani publishes Rumba Goma (org-2): ', $can('amani', 'events.publish', new ConcertEvent('rumba-goma')), "\n";
echo 'Bisimwa scans at Rumba Goma: ', $can('bisimwa', 'tickets.scan', new ConcertEvent('rumba-goma')), "\n";
echo 'Bisimwa scans at Jazz Kinshasa: ', $can('bisimwa', 'tickets.scan', new ConcertEvent('jazz-kin')), "\n";
```

- **Downward only:** an organization's owner has their roles at its events,
  but a scanner at one event is not a scanner of its organization.
- **Routes:** `#[RequiresPermission('tickets.scan', scope: 'event')]` checks
  the permission at the event named by the route parameter `{eventId}`.
  Pass `parameter:` for another name.
- **Policies** still decide first; they receive the `Scope` as the subject.
- **Events:** `RoleAssigned` and `RoleRevoked` name the user who made the
  change, and [audit](audit.md) records them.
- **Check:** `security.role_assignments` reports a missing table.
- **Opt-in:** without `role_assignments` or a store of your own, nothing
  changes.

Filtering lists by organization stays in your repositories. To check every
command against its event, see
[the recipe](../cookbook/recipes.md#checking-the-scope-of-every-command).

## New in 2.21: every route says who may reach it

`check` now holds security rules, so a mistake shows up in CI rather than in
production. The first is deny by default: every route declares who may
reach it.

```php
#[Route('/api/orders', middleware: [AuthorizationMiddleware::class])]
#[RequiresPermission('orders.view')]          // a user with this permission
final class OrderController { … }

#[Route('/api/me', middleware: [AuthorizationMiddleware::class])]
#[RequiresAuthentication]                    // any signed-in user
final class ProfileController { … }

#[PublicRoute(reason: 'The pages of the public website')]   // anyone, and why
final class PageController { … }

$router->group('', $routes, public: 'The public website');   // every route of a group
```

`#[PublicRoute]` comes from [routing](routing.md#new-in-221-public-routes). Its
reason is required, so each exception can be reviewed. A redirect counts as
public: the route it leads to has its own access.

**The rules,** as errors with `security.strict: true`, warnings otherwise:

| Id | What `check` reports |
|---|---|
| `security.route_access` | A route that says nothing about who may reach it |
| `security.access_unenforced` | `#[RequiresAuthentication]` or `#[RequiresPermission]` without `AuthorizationMiddleware` on that route |
| `security.global_middleware` | `AuthorizationMiddleware` or `RateLimitMiddleware` in `http.middleware`, where it runs before routing and enforces nothing |
| `security.csrf` | A route that changes data with session sign-in, and neither `CsrfMiddleware` nor `#[WithoutCsrf]` |
| `security.rate_limit` | A public route that changes data without `#[RateLimit]` and `RateLimitMiddleware`; always a warning |
| `security.public_disk` | A local disk inside `public/` ([filesystem](filesystem.md)); always a warning |
| `security.access_middleware` | A declaration of your own sign-in middleware that cannot be accepted (below) |
| `security.application_access` | Information: the routes that rely on your own declared sign-in middleware; a warning when it runs on none |
| `view.unescaped_object` | A template printing an object without `$this->e()` ([views](view.md)) |

- **Authentication middleware is not protection.** `AuthenticationMiddleware`
  and `SessionAuthenticationMiddleware` identify the user when there is one,
  and let anonymous requests through. Only `#[RequiresAuthentication]` or
  `#[RequiresPermission]`, enforced by `AuthorizationMiddleware` on the route,
  keeps a route closed. A rule nobody enforces looks safe and is not, which
  is why `security.access_unenforced` exists.
- **Strict mode** (`security.strict: true` in `config/security.php`) makes
  the rules errors: `check` exits with 1, and so does CI. It is off by
  default, so an upgrade fails nobody's build: an existing application gets
  warnings and opts in with that one setting. The skeletons turn it on.
- **The gate:** `release:build` runs the rules first. In strict mode an error
  stops it before anything is built; otherwise it prints them and builds.
  The skeletons' CI also runs `composer audit`, which fails on a known
  vulnerability in a dependency.
- **False alarms:** the two warnings stay warnings, because a form behind a
  CAPTCHA, or images meant to be public, are good reasons. For the errors,
  the way out is a declaration with a reason, never silence: do not add
  `#[PublicRoute]` only to quiet `check`.
- **What `check` cannot see:** whether one user may see another's invoice.
  That is business logic, for your policies and tests, and the security
  step of the workflow in `AGENTS.md`. Since 2.27,
  `assertRecordsStayWithTheirOwners()` tries every route on a record with two
  users: [testing](testing.md#new-in-227-one-users-records-stay-theirs).
- **Generators** write the declaration for you: [maker](maker.md#new-in-221-generated-routes-say-who-may-reach-them).

### Your own sign-in middleware

Not every application signs users in with Zana. A website in front of an
API keeps the API's token in its session, and its own middleware sends
visitors without one to the sign-in page; single sign-on works the same
way. Declare such a middleware, with how it refuses anonymous visitors:

```php
// config/security.php
'access_middleware' => [
    App\Web\SignedIn::class => 'Sends visitors without an API session to sign in; the API checks every permission',
],
```

```php
$router->group('/manage', $routes, [SignedIn::class]);   // these routes now count as needing a user
```

- **Only where it runs:** a route counts when the middleware is on the
  route or its group. In `http.middleware` it counts nowhere: there it is not
  tied to a route.
- **Signing in is not a permission.** The middleware counts as "needs a
  user", nothing more. `#[RequiresPermission]` still needs
  `AuthorizationMiddleware`, and what each user may do stays with your
  application or its API.
- **Shown apart:** `check` lists the routes that rely on it, with your
  reason (`security.application_access`). Zana's own protection is not in that
  list. A declared middleware that runs on no route is a warning.
- **`check` trusts your reason; the attacks test it.** `check` cannot see
  whether the middleware refuses anonymous visitors, so the reason is there
  to be reviewed. Since 2.22, `assertRoutesWithstandAttacks()` sends anonymous
  requests to every route it covers, and fails if one gets through
  ([testing](testing.md#new-in-222-every-route-attacked)).
- **Refused declarations** (`security.access_middleware`): one without a
  reason, a class that is not a middleware, and Zana's own middleware.
  `AuthenticationMiddleware` and `SessionAuthenticationMiddleware` let
  anonymous requests through, and `AuthorizationMiddleware`, `CsrfMiddleware`
  and `RateLimitMiddleware` check something else. A refused declaration
  protects nothing.
- Public routes stay public: declare them with `#[PublicRoute]`, never with
  this.

## New in 2.23: the reviewed list of what is exposed

`check` reads what each route declares, and the attacks test it. Neither
tells you that a route is new, or that a route which needed a permission
now lets in anyone signed in. `security/exposure.json` is the reviewed
baseline of who may reach each route. It is committed, so every change to
it is reviewed in a diff:

```json
{
    "about": "Who may reach each route, as reviewed. …",
    "format": 1,
    "routes": {
        "GET /api/me": {"access": "user"},
        "GET /api/orders": {"access": "permission", "permissions": ["orders.view"]},
        "GET /manage": {"access": "user", "middleware": "App\Web\SignedIn"},
        "POST /api/tokens": {"access": "public", "reason": "Signing in"},
        "GET /report": {"access": "anyone", "problem": "It requires a user, but AuthorizationMiddleware is not on the route."}
    }
}
```

**Showing and recording are separate,** so a change is never approved by
running a command:

| Command | What it does |
|---|---|
| `php bin/console security:exposure` | Shows every route that differs from the baseline. Writes nothing; exits with 1 while anything differs |
| `php bin/console security:exposure --init` | Records the first baseline; refused when one exists |
| `php bin/console security:exposure --update` | Records the changes into the baseline, listing each; refused when the baseline cannot be read |

- **`check` compares, and never writes.** `security.exposure` reports a
  route that is new, or whose access changed, as an error with
  `security.strict`, a warning otherwise. That includes a permission
  changed on a route that stays protected, e.g. "from users with
  users.manage to signed-in users". `release:build` stops on it in strict
  mode. A route that is gone is a warning.
- **What a route does, not what it says.** Each route is read as `check`
  reads it. `public` is a route that says anyone may reach it, and why.
  `anyone` is a route nothing protects: one that says nothing, or whose
  `#[RequiresPermission]` no `AuthorizationMiddleware` enforces. A change
  from one to the other is a change.
- **The review is the commit.** After adding a route or changing its access,
  run the command and read what it shows. Record it with `--update` only
  once you agree, and commit the file with the change, so a second person
  sees it in the diff. An agent never records it: see `AGENTS.md`.
- **Only the command writes it.** A baseline edited by hand, reordered, or
  naming a route twice (which could hide an entry) fails `check` in strict
  mode, even when it matches the routes. So do an unknown access or field
  and a file over 2 MB. A byte order mark and CRLF line endings, as Git and
  Windows editors write them, are accepted. If it is broken, restore it
  from Git: `--update` refuses to replace it, as that would record every
  route unreviewed.
- **Deterministic:** routes sorted by path then methods, permissions
  sorted, nothing from the environment or the machine: no path, no date,
  no setting. The same routes give the same file in every environment.
- **Without the baseline,** `check` warns, in strict mode too, so an upgrade
  fails nothing. Start with the command, review what it shows, then
  `--init`. The skeletons ship theirs.

`security:exposure` needs mj-zana/console.

## New in 2.24: spam rules

The honeypot and the time trap catch bots by how they post. Some spam is
typed by people, or by bots that wait and leave the honeypot empty: links,
known phrases, the same text again with a fresh form each time. Spam rules
read what was sent, per form:

```php
// config/security.php
'spam' => ['forms' => ['contact' => [
    'max_links' => 1,                                   // more is spam
    'blocked_terms' => ['casino', 'crypto investment'], // whole words, whatever the case
    'duplicate_minutes' => 30,                          // the same text again (a PSR-16 cache)
    'fields' => ['name', 'subject', 'message'],         // the fields read; all text fields without it
    'redirect' => '/en/contact/thanks',                 // as if it had succeeded
]]],
```

```php
#[Route('/contact', methods: ['POST'], middleware: [RateLimitMiddleware::class, SpamGuardMiddleware::class])]
#[PublicRoute(reason: 'The contact form')]
#[RateLimit(max: 5, per: 3600)]
#[SpamRules('contact')]
public function send(ServerRequestInterface $request): View|ResponseInterface { … }
```

- **Spam is treated as a filled honeypot:** sent to the form's `redirect`,
  or `security.csrf.spam_redirect`, with 303, so the bot believes it
  succeeded; otherwise refused with 403, in the same words. The handler
  never runs.
- **The form is named on the server,** in `#[SpamRules]`, never by the
  request, so a bot cannot pick a form with weaker rules. `redirect` must be
  a path on the site.
- **Points and a threshold:** each rule that finds something gives a point;
  a post with `threshold` points (1 by default) is spam.
- **Hard to dodge by typing:** blocked terms and repeated text ignore case,
  spacing and invisible characters such as zero-width spaces. A blocked term
  is a whole word: `casino` does not match "occasionally".
- **Repeated text** is kept as a hash in the cache, never the text, and only
  when the handler answered without an error. Answer a validation error with
  422 and the form, as the [website cookbook](../cookbook/website.md) does:
  a visitor who corrects the e-mail and sends the same message again goes
  through. A form that redirects back on an error would be taken for a
  repeat. `duplicate_scope: 'everyone'` compares with what anyone sent,
  which catches a bot that changes address, and refuses two people who
  write the same short text.
- **Your own rules:** a class implementing `SpamRuleInterface`, named in
  `rules`, returns a `Signal` with its points and reason. It comes from the
  container when registered there. In tests, `FixedScore` makes every post
  spam, so you can test what follows.
- **Who was refused, and why:** each refusal dispatches `SpamRefused`, with
  the rules that found something, never the text. With the audit log on, it
  is recorded as `security.spam_refused`. Read it before tightening a rule,
  and loosen one that refuses real messages. Zana's rules are recorded with
  their reasons (a count, the configured term); your own rules by name and
  points only, since a reason could quote the visitor (2.24.1). A rule's
  name must read as one (`shouting`, `no-urls`), or its class is recorded.
  `Verdict::reasons()` still has every reason, for your own code. The entry
  is kept as long as other audit entries: see
  [retention](audit.md#new-in-215-the-security-audit-log).
- **A cache that fails** never refuses a real message nor tells a visitor
  that a received one failed (2.24.1): the repeat rule finds nothing for that
  post, the other rules still apply, and the failure is logged.
- **`check` (`security.spam_rules`):** settings that cannot be used (an
  unknown setting such as `max_link`, a wrong value, a redirect off the
  site, no rule, repeats without a cache), `#[SpamRules]` with no
  `SpamGuardMiddleware` on its route, or naming a form without settings.
  Errors with `security.strict`. A form without usable settings refuses
  every post, rather than accept them unchecked.
- **Not a spam engine:** no learning, no hosted service. One can be added as
  a rule behind the same interface.

## New in 2.25: bot verification

When a form is attacked despite the honeypot, the time trap and the spam
rules, or bots race people for a purchase, such as tickets the moment sales
open, ask a CAPTCHA provider whether a person sent it. Security holds the
rule; [mj-zana/captcha](https://github.com/mjnable/zana-captcha) holds the
providers: Cloudflare Turnstile, Google reCAPTCHA (v2, and v3 with a score)
and hCaptcha.

```php
#[Route('/checkout', methods: ['POST'], middleware: [RateLimitMiddleware::class, BotVerificationMiddleware::class])]
#[VerifyHuman(action: 'checkout', minimumScore: 0.5)]   // minimumScore: reCAPTCHA v3
public function pay(ServerRequestInterface $request): ResponseInterface { … }
```

```php
<?= $this->captcha_widget('checkout', $csp_nonce) ?>   <!-- in the form -->
```

```php
// config/captcha.php; the secret and site key from APP_CAPTCHA__SECRET, APP_CAPTCHA__SITE_KEY
return ['provider' => 'turnstile'];
```

- **Before the handler,** the token, from the widget's field or an
  `X-Bot-Token` header for an API, is sent to the provider with the client
  address. Refused with 403: no token; the provider says no; a score below
  `minimumScore`; a token made for another action; with
  `security.bot.hostnames`, one made on another site.
- **Expectations are strict (2.25.1).** When `#[VerifyHuman]` names an
  action, a provider that reports actions (Turnstile, reCAPTCHA v3) must
  return it: an answer without one is refused. With `security.bot.hostnames`,
  an answer without a hostname is refused (every provider reports it).
  Where a provider cannot report what you expect (reCAPTCHA v2 and hCaptcha
  name no action), `check` warns that it cannot be enforced.
- **A token is used once.** All three providers refuse a token they have
  already verified (`timeout-or-duplicate`), and the request is refused.
  Do not retry a verification: a retried token is a reused one.
- **Three kinds of failure,** logged apart, and never with the token nor a
  verifier's exception message (2.25.1):

  | Kind | Log | Refused |
  |---|---|---|
  | Misconfigured: no or a wrong secret, no HTTP client, no provider, a verifier that throws | error | always |
  | The provider out of reach: a timeout, a server error, an unreadable answer | warning | unless `security.bot.on_unavailable: 'allow'` |
  | No person: no token, the provider says no, a low score, another action or site | notice | always |

  `allow` is for riding out a provider's outage, where losing a message is
  worse than letting a bot in. It never lets requests through a
  misconfiguration: that would hide a missing secret indefinitely.
- **A PSR-18 client** is needed to ask the provider, which you install and
  bound: a time to connect, a time to wait for the answer, a total, and no
  retries. Symfony's client waits as long as PHP's `default_socket_timeout`
  (usually 60 s) between bytes, and sets no total by default:

  ```php
  // composer require symfony/http-client nyholm/psr7
  $services->singleton(\Psr\Http\Client\ClientInterface::class, static fn () => new \Symfony\Component\HttpClient\Psr18Client(
      \Symfony\Component\HttpClient\HttpClient::create([
          'max_connect_duration' => 2,   // DNS, TCP and TLS (Symfony 8.1 or later)
          'timeout' => 3,                // between two bytes of the answer
          'max_duration' => 5,           // the whole request and answer
      ]),
  ));
  ```

  Do not wrap it in a retrying client. A slow provider then refuses the
  post within seconds (or lets it through, with `on_unavailable: 'allow'`)
  instead of holding the visitor. No provider SDK is needed.
- **The provider is a setting:** change `captcha.provider` and the widget's
  helper and the verification follow.
- **Who was refused:** `BotVerificationFailed`, recorded by the audit log as
  `security.bot_verification_failed`, with the reasons and score, never the
  token.
- **In tests,** register `FakeBotVerifier` as `BotVerifierInterface`:
  `new FakeBotVerifier(['person' => true])`, then post `bot_token=person`.
  No test calls a provider. `NullBotVerifier` accepts everything, for
  development only.
- **`check`:** `#[VerifyHuman]` with no middleware on its route, no verifier,
  or a verifier that asks nobody in production (`security.bot_verification`);
  and, with mj-zana/captcha, no provider or secret (`captcha.config`), no
  PSR-18 client (`captcha.http_client`), or a Content-Security-Policy that
  blocks the widget (`captcha.csp`). The default policy, `default-src 'self'`,
  blocks every provider, and a blocked widget sends no token, so every
  visitor would be refused: `check` says what to add. It reads the sources
  each provider documents, by directive: Turnstile's script and frame;
  reCAPTCHA's script, frames (two hosts) and connections, which may be
  scoped to `/recaptcha/` as Google writes them, and v3's inline script,
  which needs the nonce; hCaptcha's script, frame, style and connections.

**Spam rules, too (2.25):** the same text sent while the first copy is still
being handled is now caught: it is reserved as soon as the rules let a post
through, and released if the handler fails. Since 2.29 it is claimed in one
step on a Zana cache (`add()`), so two posts at the very same instant cannot
both pass either: twenty identical posts at once pass once. On another
PSR-16 cache they still can, and `check` warns (`security.atomic_cache`).
An application's own reserving rule can claim too, with
`ClaimingSpamRuleInterface`; one written for 2.25 reserves as before.
`php bin/console audit:spam --days=7` summarises refusals by form and rule
([audit](audit.md#new-in-215-the-security-audit-log)).

## New in 2.26: limits that hold under concurrent requests

Until 2.26, a rate limit read its count and then wrote it back, so requests
arriving at the same instant all read the same count. Twenty sent at once
all passed a limit of five, and a single-use CSRF token could be accepted
several times. That made the limit on sign-in attempts easy to multiply
with parallel requests.

- **On a Zana cache** (`cache.default: file` or `array`), the count is now
  taken and decided in one step (`increment()`), and a single-use token is
  claimed in one (`add()`): twenty at once pass a limit of five exactly five
  times, and a token is accepted once. A refused attempt takes its count
  back, so limits count as they did one request at a time.
- **Nothing to change** in an application that uses mj-zana/cache: run
  `composer update`.
- **Another PSR-16 cache** (a Symfony or Laravel adapter, for example)
  keeps the old behaviour. `check` warns (`security.atomic_cache`) when a
  rate limit, sign-in, single-use tokens or the spam rules rely on it. Use a
  Zana store, or a cache implementing `Zana\Cache\AtomicCacheInterface`,
  for example on Redis `INCR` and `SET NX`.

## New in 2.28: lockouts, known devices, and tokens tied to a session

### A guesser no longer locks the owner out

Until 2.28, five wrong passwords for a login, from anywhere, locked that
login for the window: anyone who knew an e-mail address could keep its
owner out. Now `SessionLogin` counts:

| Limit | Default | Holds |
|---|---|---|
| A login from one address | `security.login.max_attempts`, 5 per `security.login.window` | that address, for that login: the guesser, not the owner |
| A login from every address together | `security.login.max_attempts_per_login`, 10 × the above | guessing from many addresses |
| Failed attempts from one address, any login | `security.login.max_attempts` | one address trying many logins (since 2.19.1) |

To lock the owner out, a guesser now needs many addresses, and even then a
known device gets through.

### Known devices

Each sign-in returns a token for a "known device" cookie. Send it back, and
that browser is not held by the limit on the login from every address:

```php
$result = $login->start($email, $password, $context->clientIp(), knownDevice: $cookies['known_device'] ?? null);
if ($result->knownDevice !== null) {
    // Set it: HttpOnly, Secure, SameSite=Lax, for security.login.known_device_days (90 by default).
}
```

- It is for that login only, and never replaces the password. The limit
  from its own address still holds.
- It names the user by a hash of their identifier, so the cookie says
  nothing about who they are. No other token of Zana's counts as one.
- Applications that do not set the cookie get the new limits only.

### CSRF tokens tied to the session (opt-in)

CSRF tokens are stateless, so a token one visitor got from the site passes
for another visitor. The other defences (`Sec-Fetch-Site`, `Origin` and
SameSite cookies) stop most of it; a browser that sends none of them relied
on SameSite alone. With

```php
'csrf' => ['bind_to_session' => true],
```

a visitor who has a session gets tokens tied to it, and a request that has
a session is accepted only with such a token:

- **Another visitor's token is refused**, also from another signed-in user.
  On MMC's back office, a second administrator's token no longer changed
  the first one's settings or signed them out.
- **A visitor without a session** keeps stateless tokens, so no session, and
  no cookie, is started for a form.
- **Put `CsrfMiddleware` after `SessionMiddleware`, and on the pages that
  show the forms too** (it lets GET requests through): it sets the session
  the tokens are tied to. A form printed elsewhere, or before the visitor had
  a session, is refused once, and shown again.
- **Cached pages:** a form on a page the page cache keeps was printed for
  someone without a session; a visitor with one cannot post it. Keep such
  forms off cached pages, or leave binding off.
- Off by default, so nothing changes until you turn it on.

<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Security\Authorizer;
use Zana\Security\InvalidTokenException;
use Zana\Security\PasswordHasher;
use Zana\Security\PolicyInterface;
use Zana\Security\TokenSigner;
use Zana\Security\UserInterface;

final readonly class User implements UserInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(private string $id, private array $roles)
    {
    }

    public function identifier(): string
    {
        return $this->id;
    }

    public function roles(): array
    {
        return $this->roles;
    }
}

final readonly class Note
{
    public function __construct(public string $authorId)
    {
    }
}

// region: passwords
$hasher = new PasswordHasher();                  // Argon2id when available, otherwise bcrypt
$hash = $hasher->hash('correct horse battery staple');
var_export($hasher->verify('correct horse battery staple', $hash));
echo "\n";
var_export($hasher->verify('wrong', $hash));
echo "\n";
// endregion: passwords

// region: tokens
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
// endregion: tokens

// region: authorization
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
// endregion: authorization

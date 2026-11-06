<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Cache\ArrayCache;
use Zana\Security\PasswordHasher;
use Zana\Security\PasswordUserProviderInterface;
use Zana\Security\RateLimit\RateLimiter;
use Zana\Security\SecurityContext;
use Zana\Security\Session\SessionLogin;
use Zana\Security\TokenSigner;
use Zana\Security\TwoFactor\Totp;
use Zana\Security\TwoFactor\TwoFactor;
use Zana\Security\TwoFactorUserProviderInterface;
use Zana\Security\UserInterface;
use Zana\Session\Session;

final readonly class Staff implements UserInterface
{
    public function __construct(public string $id, public string $email)
    {
    }

    public function identifier(): string
    {
        return $this->id;
    }

    public function roles(): array
    {
        return ['organizer'];
    }
}

// region: provider
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
// endregion: provider

$now = 1_800_000_000;
$clock = static function () use (&$now): int {
    return $now;
};
$hasher = new PasswordHasher(['cost' => 4], PASSWORD_BCRYPT);
$users = new StaffUsers();
$amani = new Staff('7', 'amani@example.com');
$users->rows['7'] = ['user' => $amani, 'hash' => $hasher->hash('correct horse'), 'two_factor' => null];

// In an application, the provider builds TwoFactor from config (security.two_factor.key and friends).
$totp = new Totp($clock);
$twoFactor = new TwoFactor(
    $users,
    str_repeat('k', 64),   // security.two_factor.key
    new TokenSigner(str_repeat('s', 32), $clock),
    $totp,
    new RateLimiter(new ArrayCache(), $clock),
    issuer: 'Billets RDC',
);
$app = static fn (string $secret): string => $totp->code($secret);   // what the authenticator app shows

// region: enrol
$enrolment = $twoFactor->enrol($amani, $amani->email);
echo 'Scan: ', substr($enrolment->uri, 0, 40), "...\n";        // show it as a QR code
echo 'Recovery codes: ', count($enrolment->recoveryCodes), "\n";
echo 'Confirmed: ', $twoFactor->confirm($amani, $app($enrolment->secret)) ? 'yes' : 'no', "\n";
// endregion: enrol

$now += 30;
$session = new Session();
$context = new SecurityContext();
$login = new SessionLogin($users, $hasher, $session, $context, twoFactor: $twoFactor, clock: $clock);

// region: sign-in
$result = $login->start('amani@example.com', 'correct horse', '198.51.100.7');
echo 'After the password: ', $result->status, ', signed in as ', $context->user()?->identifier() ?? 'nobody', "\n";

$result = $login->completeSecondFactor('123456', '198.51.100.7');
echo 'A wrong code: ', $result->status, "\n";

$result = $login->completeSecondFactor($app($enrolment->secret), '198.51.100.7', remember: true);
echo 'The code from the app: ', $result->status, ', signed in as ', $context->user()?->identifier() ?? 'nobody', "\n";
echo 'Device token for a cookie: ', $result->deviceToken === null ? 'no' : 'yes', "\n";
// endregion: sign-in

<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Validation\Constraint\Choice;
use Zana\Validation\Constraint\Email;
use Zana\Validation\Constraint\Length;
use Zana\Validation\Constraint\NotBlank;
use Zana\Validation\Constraint\Range;
use Zana\Validation\Constraint\Valid;
use Zana\Validation\ValidationException;
use Zana\Validation\Validator;

// region: constraints
final readonly class Address
{
    public function __construct(
        #[NotBlank] public string $city,
        #[Length(min: 4, max: 10)] public string $postcode,
    ) {
    }
}

final readonly class SignUp
{
    public function __construct(
        #[NotBlank(message: 'Tell us your name.'), Length(max: 50)] public string $name,
        #[Email] public string $email,
        #[Range(min: 18, max: 130)] public int $age,
        #[Choice(['basic', 'pro'])] public string $plan,
        #[Valid] public Address $address,          // validate the nested object too
        #[Email] public ?string $backupEmail = null,   // null passes everything except NotBlank and NotNull
    ) {
    }
}
// endregion: constraints

// region: validate
$validator = new Validator();

$violations = $validator->validate(new SignUp('', 'not-an-email', 15, 'gold', new Address('', '12')));
echo count($violations), " violations\n";
foreach ($violations->toArray() as $path => $messages) {
    echo "{$path}: ", implode(' ', $messages), "\n";
}

try {
    $validator->assertValid(new SignUp('Ada', 'ada@example.com', 36, 'pro', new Address('London', 'EC1A')));
    echo "valid\n";
} catch (ValidationException $e) {
    echo $e->getMessage(), "\n";
}
// endregion: validate

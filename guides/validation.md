# Validation

`mj-zana/validation` checks objects against constraint attributes and
reports every violation with its path.

```
composer require mj-zana/validation
```

## Constraints

Put constraints on properties, including promoted constructor properties:

<!-- example: examples/guides/validation.php#constraints -->
```php
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
```

## Validating

<!-- example: examples/guides/validation.php#validate -->
```php
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
```

<!-- example: examples/guides/validation.out -->
```text
6 violations
name: Tell us your name.
email: This value is not a valid email address.
age: This value should be between 18 and 130.
plan: The value you selected is not a valid choice.
address.city: This value should not be blank.
address.postcode: This value is too short. It should have 4 characters or more.
valid
```

## Rules worth knowing

- **Constraints:** `NotBlank`, `NotNull`, `Length`, `Range`, `Positive`,
  `Email`, `Url` (http and https), `Regex`, `Choice` (strict comparison),
  `Count`, and `Valid` for nested objects and arrays of objects. Paths look
  like `address.city` or `items[2].name`.
- **`null` passes** every constraint except `NotBlank` and `NotNull`, so
  optional fields need no special care.
- **Messages:** every constraint takes a `message:`, with `{placeholders}`
  for its options, e.g. `{min}`.
- **Paths use property names.** If your API exposes other names, e.g.
  `height_cm` through serialization's `#[SerializedName]`, map them when
  you report errors.
- **In an application:**
  - register `ValidationServiceProvider` and inject `Validator`;
  - put constraints on your [mediator](mediator.md) commands, and validate
    them all in one pipeline behaviour.
- **Validation and serialization work as a pair.**
  [Serialization](serialization.md) turns raw input into a typed command
  and reports type errors. Validation then checks the rules on that typed
  value.

## New in 2.1: translated messages

Each violation keeps its `template` and `parameters`. With
`mj-zana/translation` installed, messages are translated in the request's
language, so custom messages can be keys:
`#[NotBlank(message: 'contact.error.name')]` ([translation](translation.md)).

## Validating every message (2.10)

With `mj-zana/mediator`, `ValidationBehavior` validates every command and
query before its handler runs, whatever sends it: an API action, a console
command, a queue job or another handler. It is opt-in: list it in
`config/mediator.php`.

<!-- example: examples/guides/validation-behavior.php#enable -->
```php
$app = Application::configure(sys_get_temp_dir() . '/zana-docs-validation-behavior')
    ->withProviders(HttpServiceProvider::class, MediatorServiceProvider::class, ValidationServiceProvider::class)
    ->withConfiguration(['mediator.behaviors' => [ValidationBehavior::class]])   // config/mediator.php
    ->withServices(static fn (ServiceCollectionInterface $s) => $s->scoped(InviteMemberHandler::class))
    ->build();
$app->container()->get(HandlerMap::class)->register(InviteMemberHandler::class);
```

<!-- example: examples/guides/validation-behavior.php#send -->
```php
// From a console command, a queue job or another handler: the handler never sees invalid input.
$scope = $app->container()->createScope();
$mediator = $scope->get(MediatorInterface::class);

echo $mediator->send(new InviteMember('ada@example.com', 'Ada')), "\n";
try {
    $mediator->send(new InviteMember('ada at example.com', ''));
} catch (ValidationException $e) {
    foreach ($e->violations->toArray() as $field => $messages) {
        echo "{$field}: ", implode(' ', $messages), "\n";
    }
}
```

<!-- example: examples/guides/validation-behavior.php#http -->
```php
// Over HTTP, the same exception becomes a 422 with the field errors.
$response = $scope->get(ErrorMiddleware::class)->render($e, new ServerRequest('POST', '/members', ['Accept' => 'application/json']));

echo $response->getStatusCode(), ' ', json_encode(json_decode((string) $response->getBody(), true)['errors']), "\n";
```

<!-- example: examples/guides/validation-behavior.out -->
```text
Invited ada@example.com.
email: This value is not a valid email address.
name: This value should not be blank.
422 {"email":["This value is not a valid email address."],"name":["This value should not be blank."]}
```

- **Over HTTP:** with `mj-zana/http`, a `ValidationException` that reaches
  `ErrorMiddleware` is a 422 with `errors` per field, and so is a
  `DenormalizationException` of `mj-zana/serialization` (a missing or
  mistyped field). This is the same shape as the generated `ApiInput`
  (2.8), which keeps validating so that a generated slice works with or
  without the behaviour. See [exception mappers](http.md#new-in-210-exception-mappers).
- **In the console:** a `ValidationException` is printed field by field.
- **Paths are property paths** (`dateOfBirth`). When the JSON names differ
  (`#[SerializedName('date_of_birth')]`), catch the exception and rename
  the paths, as the [clinic](../cookbook/modules.md) does in its
  `JsonInput`.

Reference: the [validation README](https://github.com/mjnable/zana-validation).

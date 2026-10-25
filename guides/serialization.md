# Serialization

`mj-zana/serialization` turns objects into arrays and JSON, and hydrates
typed objects from arrays and JSON through their constructors, collecting
every error on the way.

```
composer require mj-zana/serialization
```

## Classes

Hydration goes through the constructor. Attributes rename fields, describe
lists, hide properties, and replace error messages:

<!-- example: examples/guides/serialization.php#classes -->
```php
final readonly class Reading
{
    public function __construct(
        #[SerializedName('height_cm'), ErrorMessage(invalid: 'Must be a number.', missing: 'Height is required.')]
        public float $heightCm,
        public \DateTimeImmutable $takenAt,
    ) {
    }
}

final readonly class Patient
{
    /**
     * @param list<Reading> $readings
     */
    public function __construct(
        public string $name,
        public Unit $unit,
        #[ListOf(Reading::class)] public array $readings = [],
        #[Ignore] public ?string $internalNote = null,
    ) {
    }
}
```

## Round trip

<!-- example: examples/guides/serialization.php#round-trip -->
```php
$serializer = new Serializer();

$json = '{"name": "Ada", "unit": "metric", "readings": [{"height_cm": "170.5", "takenAt": "2026-10-25T09:00:00+00:00"}]}';
$patient = $serializer->deserialize($json, Patient::class);
echo $patient->readings[0]->heightCm, ' cm on ', $patient->readings[0]->takenAt->format('Y-m-d'), "\n";

echo $serializer->serialize($patient), "\n";
```

## Every error, by path

<!-- example: examples/guides/serialization.php#errors -->
```php
try {
    $serializer->denormalize(['unit' => 'stone', 'readings' => [['takenAt' => 'never'], ['height_cm' => 'tall', 'takenAt' => '2026-01-01']]], Patient::class);
} catch (DenormalizationException $e) {
    foreach ($e->errors() as $path => $message) {   // every problem, by path
        echo "{$path}: {$message}\n";
    }
}
```

<!-- example: examples/guides/serialization.out -->
```text
170.5 cm on 2026-10-25
{"name":"Ada","unit":"metric","readings":[{"height_cm":170.5,"takenAt":"2026-10-25T09:00:00+00:00"}]}
name: This field is missing.
unit: The value should be one of: metric, imperial.
readings[0].height_cm: Height is required.
readings[0].takenAt: This value is not a valid date.
readings[1].height_cm: Must be a number.
```

## Rules worth knowing

- **Type conversion:**
  - numeric strings become `int` and `float`, and an int is accepted for a
    float;
  - `"true"`, `"false"`, `"1"` and `"0"` become booleans;
  - backed enums are built from their value, unit enums from their name;
  - dates are parsed from strings.
  Anything else of the wrong type is an error.
- **Missing fields:** a missing field uses the constructor default, then
  `null` if the type allows it; otherwise it is an error. Unknown fields
  are ignored.
- **`#[ErrorMessage]`:** it replaces the messages for one field. `invalid`
  covers every conversion failure of the field itself; errors inside a
  nested object keep their own messages.
- **Normalization:** objects become their public properties, enums their
  value or name, and dates ATOM strings. `JsonSerializable` objects are
  serialised first. JSON keeps a float's `.0`, so `170.0` stays a float.
- **In an application:**
  - register `SerializationServiceProvider` and inject `Serializer`;
  - turn request bodies and console arguments into typed commands with
    `denormalize()`, then let [validation](validation.md) check the rules.

Reference: the [serialization README](https://github.com/mjnable/zana-serialization).

<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Zana\Serialization\Attribute\ErrorMessage;
use Zana\Serialization\Attribute\Ignore;
use Zana\Serialization\Attribute\ListOf;
use Zana\Serialization\Attribute\SerializedName;
use Zana\Serialization\DenormalizationException;
use Zana\Serialization\Serializer;

enum Unit: string
{
    case Metric = 'metric';
    case Imperial = 'imperial';
}

// region: classes
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
// endregion: classes

// region: round-trip
$serializer = new Serializer();

$json = '{"name": "Ada", "unit": "metric", "readings": [{"height_cm": "170.5", "takenAt": "2026-10-25T09:00:00+00:00"}]}';
$patient = $serializer->deserialize($json, Patient::class);
echo $patient->readings[0]->heightCm, ' cm on ', $patient->readings[0]->takenAt->format('Y-m-d'), "\n";

echo $serializer->serialize($patient), "\n";
// endregion: round-trip

// region: errors
try {
    $serializer->denormalize(['unit' => 'stone', 'readings' => [['takenAt' => 'never'], ['height_cm' => 'tall', 'takenAt' => '2026-01-01']]], Patient::class);
} catch (DenormalizationException $e) {
    foreach ($e->errors() as $path => $message) {   // every problem, by path
        echo "{$path}: {$message}\n";
    }
}
// endregion: errors

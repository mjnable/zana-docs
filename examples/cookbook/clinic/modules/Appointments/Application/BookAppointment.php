<?php

declare(strict_types=1);

namespace Modules\Appointments\Application;

use Zana\Ddd\Application\Command;
use Zana\Serialization\Attribute\ErrorMessage;
use Zana\Serialization\Attribute\SerializedName;
use Zana\Validation\Constraint\Length;
use Zana\Validation\Constraint\NotBlank;

// region: command
/**
 * Built from JSON by the serializer, validated by the mediator before the handler runs.
 */
final class BookAppointment extends Command
{
    public function __construct(
        #[SerializedName('patient_id'), NotBlank] public readonly string $patientId,
        #[SerializedName('starts_at'), ErrorMessage(invalid: 'Must be a date and time.')] public readonly \DateTimeImmutable $startsAt,
        #[NotBlank, Length(max: 200)] public readonly string $reason,
    ) {
        parent::__construct();
    }
}
// endregion: command

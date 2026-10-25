<?php

declare(strict_types=1);

namespace Modules\Patients\Application;

use Zana\Ddd\Application\Command;
use Zana\Validation\Constraint\Length;
use Zana\Validation\Constraint\NotBlank;

final class RegisterPatient extends Command
{
    public function __construct(
        #[NotBlank, Length(max: 100)] public readonly string $name,
    ) {
        parent::__construct();
    }
}

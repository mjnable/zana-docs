<?php

declare(strict_types=1);

namespace Clinic\Tests;

use PHPUnit\Framework\TestCase;
use Zana\Testing\Architecture\ArchitectureAssertions;
use Zana\Testing\Architecture\ModuleRules;

// region: architecture-test
final class ArchitectureTest extends TestCase
{
    use ArchitectureAssertions;

    public function testEveryModuleFollowsTheDependencyRules(): void
    {
        $rules = ModuleRules::for(dirname(__DIR__) . '/modules');

        $this->assertSame(['Appointments', 'Patients'], $rules->modules());
        self::assertModuleArchitecture($rules);
    }
}
// endregion: architecture-test

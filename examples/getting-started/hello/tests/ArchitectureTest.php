<?php

declare(strict_types=1);

namespace Hello\Tests;

use PHPUnit\Framework\TestCase;
use Zana\Testing\Architecture\ArchitectureAssertions;
use Zana\Testing\Architecture\ArchitectureRule;
use Zana\Testing\Architecture\ModuleRules;

final class ArchitectureTest extends TestCase
{
    use ArchitectureAssertions;

    public function testEveryModuleFollowsTheDependencyRules(): void
    {
        self::assertModuleArchitecture(ModuleRules::for(dirname(__DIR__) . '/modules'));
    }

    public function testTheApplicationCodeStaysOutOfTheModules(): void
    {
        self::assertArchitecture(
            ArchitectureRule::classesIn('Hello')->mustNotDependOn('Modules'),
            dirname(__DIR__) . '/src',
        );
    }
}

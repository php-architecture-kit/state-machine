<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\RaceFirst;

use Deprecated;
use PhpArchitecture\StateMachine\Foundation\Component\RaceFirst\RaceFirstComponent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class RaceFirstComponentTest extends TestCase
{
    #[Test]
    public function createIsDeprecatedInFavourOfRaceComponent(): void
    {
        $attributes = (new ReflectionMethod(RaceFirstComponent::class, 'create'))->getAttributes(Deprecated::class);

        $this->assertCount(1, $attributes);
        $this->assertStringContainsString('RaceComponent', $attributes[0]->newInstance()->message ?? '');
    }

    #[Test]
    public function defineBuildsTheSameComponentWithoutTheNotice(): void
    {
        $this->assertEmpty((new ReflectionMethod(RaceFirstComponent::class, 'define'))->getAttributes(Deprecated::class));
        $this->assertInstanceOf(RaceFirstComponent::class, RaceFirstComponent::define('unit'));
    }
}

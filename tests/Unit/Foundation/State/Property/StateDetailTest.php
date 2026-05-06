<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\State\Property;

use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StateDetailTest extends TestCase
{
    #[Test]
    public function constructorStoresNameAndValue(): void
    {
        $detail = new StateDetail('amount', 42);

        $this->assertSame('amount', $detail->name);
        $this->assertSame(42, $detail->value);
    }

    #[Test]
    public function constructorAcceptsNullValue(): void
    {
        $detail = new StateDetail('flag', null);

        $this->assertSame('flag', $detail->name);
        $this->assertNull($detail->value);
    }

    #[Test]
    public function constructorAcceptsStringValue(): void
    {
        $detail = new StateDetail('status', 'active');

        $this->assertSame('active', $detail->value);
    }

    #[Test]
    public function constructorAcceptsArrayValue(): void
    {
        $detail = new StateDetail('items', ['a', 'b']);

        $this->assertSame(['a', 'b'], $detail->value);
    }

    #[Test]
    public function withValueReturnsNewInstanceWithSameName(): void
    {
        $original = new StateDetail('amount', 10);

        $updated = $original->withValue(99);

        $this->assertNotSame($original, $updated);
        $this->assertSame('amount', $updated->name);
    }

    #[Test]
    public function withValueReturnsNewInstanceWithProvidedValue(): void
    {
        $original = new StateDetail('amount', 10);

        $updated = $original->withValue(99);

        $this->assertSame(99, $updated->value);
    }

    #[Test]
    public function withValueDoesNotMutateOriginal(): void
    {
        $original = new StateDetail('amount', 10);

        $original->withValue(99);

        $this->assertSame(10, $original->value);
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\State\Property;

use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use LogicException;
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

    #[Test]
    public function mapAllKeysPlainValuesAndStateDetailsByDetailName(): void
    {
        $mapped = StateDetail::mapAll('car', ['floor' => 3, new StateDetail('doors', 'open'), 'stops' => [1, 2]], LogicException::class);

        $this->assertEquals([
            'floor' => new StateDetail('floor', 3),
            'doors' => new StateDetail('doors', 'open'),
            'stops' => new StateDetail('stops', [1, 2]),
        ], $mapped);
    }

    #[Test]
    public function mapAllKeepsTheNameTheAuthorGaveTheDetail(): void
    {
        $detail = new StateDetail('0', 'whatever');

        $this->assertSame(['0' => $detail], StateDetail::mapAll('car', [$detail], LogicException::class));
    }

    #[Test]
    public function mapAllLetsALaterDetailWithTheSameNameWin(): void
    {
        $mapped = StateDetail::mapAll('car', ['floor' => 3, new StateDetail('floor', 4)], LogicException::class);

        $this->assertSame(4, $mapped['floor']->value);
    }

    #[Test]
    public function mapAllRejectsAPlainValueWithoutADetailName(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('State "car": the detail at index 1 has no name, so it must be a ' . StateDetail::class . ', string given.');

        StateDetail::mapAll('car', ['floor' => 3, 1 => 'doors'], LogicException::class);
    }
}

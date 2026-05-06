<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\State;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\State\Identity\StateId;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\StateMachine\Foundation\State\State;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;
use stdClass;

class StateTest extends TestCase
{
    #[Test]
    public function createGeneratesNonNullId(): void
    {
        $state = State::create(ExecutionId::new(), 'order', []);

        $this->assertInstanceOf(StateId::class, $state->id);
    }

    #[Test]
    public function createReturnsDifferentIdOnEachCall(): void
    {
        $executionId = ExecutionId::new();

        $a = State::create($executionId, 'order', []);
        $b = State::create($executionId, 'order', []);

        $this->assertFalse($a->id->equals($b->id));
    }

    #[Test]
    public function createStoresExecutionId(): void
    {
        $executionId = ExecutionId::new();

        $state = State::create($executionId, 'order', []);

        $this->assertTrue($executionId->equals($state->executionId));
    }

    #[Test]
    public function createStoresName(): void
    {
        $state = State::create(ExecutionId::new(), 'payment', []);

        $this->assertSame('payment', $state->name);
    }

    #[Test]
    public function createIndexesDetailsByName(): void
    {
        $details = [
            new StateDetail('amount', 100),
            new StateDetail('currency', 'USD'),
        ];

        $state = State::create(ExecutionId::new(), 'order', $details);

        $this->assertArrayHasKey('amount', $state->details);
        $this->assertArrayHasKey('currency', $state->details);
    }

    #[Test]
    public function createStoresDetailValues(): void
    {
        $detail = new StateDetail('amount', 100);

        $state = State::create(ExecutionId::new(), 'order', [$detail]);

        $this->assertSame(100, $state->details['amount']->value);
    }

    #[Test]
    public function createThrowsWhenDetailsContainNonStateDetailInstance(): void
    {
        $this->expectException(Throwable::class);

        State::create(ExecutionId::new(), 'order', [new stdClass()]);
    }

    #[Test]
    public function createAcceptsEmptyDetails(): void
    {
        $state = State::create(ExecutionId::new(), 'order', []);

        $this->assertEmpty($state->details);
    }

    #[Test]
    public function recreateUsesProvidedId(): void
    {
        $id = StateId::new();
        $executionId = ExecutionId::new();

        $state = State::recreate($executionId, $id, 'order', []);

        $this->assertTrue($id->equals($state->id));
    }

    #[Test]
    public function recreatePreservesNameAndDetails(): void
    {
        $details = [new StateDetail('qty', 3)];

        $state = State::recreate(ExecutionId::new(), StateId::new(), 'cart', $details);

        $this->assertSame('cart', $state->name);
        $this->assertArrayHasKey('qty', $state->details);
    }
}

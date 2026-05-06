<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Execution;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointers;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExecutionTest extends TestCase
{
    #[Test]
    public function createReturnsExecutionWithNonNullId(): void
    {
        $execution = Execution::create();

        $this->assertInstanceOf(ExecutionId::class, $execution->id);
    }

    #[Test]
    public function createReturnsExecutionWithEmptyPointers(): void
    {
        $execution = Execution::create();

        $this->assertInstanceOf(Pointers::class, $execution->pointers);
        $this->assertEmpty($execution->pointers->pointers);
    }

    #[Test]
    public function createReturnsExecutionWithEmptyStates(): void
    {
        $execution = Execution::create();

        $this->assertInstanceOf(States::class, $execution->states);
        $this->assertEmpty($execution->states->states);
    }

    #[Test]
    public function createPointersAreLinkedToSameExecutionId(): void
    {
        $execution = Execution::create();

        $this->assertTrue($execution->id->equals($execution->pointers->executionId));
        $this->assertTrue($execution->id->equals($execution->states->executionId));
    }

    #[Test]
    public function createReturnsDifferentIdOnEachCall(): void
    {
        $a = Execution::create();
        $b = Execution::create();

        $this->assertFalse($a->id->equals($b->id));
    }

    #[Test]
    public function recreateUsesProvidedComponents(): void
    {
        $id = ExecutionId::new();
        $pointers = Pointers::create($id, null, null, null);
        $states = States::create($id, null, null, null);

        $execution = Execution::recreate($id, $pointers, $states);

        $this->assertSame($id, $execution->id);
        $this->assertSame($pointers, $execution->pointers);
        $this->assertSame($states, $execution->states);
    }
}

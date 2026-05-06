<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Transition\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\RejectStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RejectStrategyTest extends TestCase
{
    private function makeOutput(array $goto, array $waitfor = [], array $reject = []): TransitionSelectionOutput
    {
        return new TransitionSelectionOutput($goto, $waitfor, $reject);
    }

    private function makeTransition(): Transition
    {
        return Transition::create(NodeId::new(), NodeId::new());
    }

    #[Test]
    public function supportsReturnsTrueWhenOnlyRejectIsPresent(): void
    {
        $strategy = new RejectStrategy();
        $output = $this->makeOutput([], [], [$this->makeTransition()]);

        $this->assertTrue($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsTrueForMultipleRejectTransitions(): void
    {
        $strategy = new RejectStrategy();
        $output = $this->makeOutput([], [], [$this->makeTransition(), $this->makeTransition()]);

        $this->assertTrue($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseWhenRejectIsEmpty(): void
    {
        $strategy = new RejectStrategy();
        $output = $this->makeOutput([]);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseWhenGotoIsPresent(): void
    {
        $strategy = new RejectStrategy();
        $output = $this->makeOutput([$this->makeTransition()], [], [$this->makeTransition()]);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseWhenWaitforIsPresent(): void
    {
        $strategy = new RejectStrategy();
        $output = $this->makeOutput([], [$this->makeTransition()], [$this->makeTransition()]);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function transitionToNextNodesRemovesPointerFromCollection(): void
    {
        $strategy = new RejectStrategy();
        $execution = Execution::create();
        $pointer = $execution->pointers->startAt(NodeId::new());
        $output = $this->makeOutput([], [], [$this->makeTransition()]);

        $strategy->transitionToNextNodes($execution, $pointer, $output);

        $this->assertEmpty($execution->pointers->pointers);
        $this->assertArrayNotHasKey($pointer->id->toString(), $execution->pointers->pointers);
    }

    #[Test]
    public function transitionToNextNodesOnlyRemovesTheGivenPointer(): void
    {
        $strategy = new RejectStrategy();
        $execution = Execution::create();
        $pointer1 = $execution->pointers->startAt(NodeId::new());
        $pointer2 = $execution->pointers->startAt(NodeId::new());
        $output = $this->makeOutput([], [], [$this->makeTransition()]);

        $strategy->transitionToNextNodes($execution, $pointer1, $output);

        $this->assertCount(1, $execution->pointers->pointers);
        $this->assertArrayHasKey($pointer2->id->toString(), $execution->pointers->pointers);
    }
}

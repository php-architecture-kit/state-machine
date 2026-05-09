<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Transition\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\WaitStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WaitStrategyTest extends TestCase
{
    private function makeOutput(array $goto, array $waitfor = [], array $reject = []): TransitionSelectionOutput
    {
        return new TransitionSelectionOutput($goto, $waitfor, $reject);
    }

    private function makeTransition(): Transition
    {
        return Transition::create(NodeId::create("state-machine.unit.foundation.transition.strategy.default.waitst.node1"), NodeId::create("state-machine.unit.foundation.transition.strategy.default.waitst.node2"));
    }

    #[Test]
    public function supportsReturnsTrueWhenWaitforIsNotEmptyAndGotoIsEmpty(): void
    {
        $strategy = new WaitStrategy();
        $output = $this->makeOutput([], [$this->makeTransition()]);

        $this->assertTrue($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsTrueForMultipleWaitforAndEmptyGoto(): void
    {
        $strategy = new WaitStrategy();
        $output = $this->makeOutput([], [$this->makeTransition(), $this->makeTransition()]);

        $this->assertTrue($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseWhenWaitforIsEmpty(): void
    {
        $strategy = new WaitStrategy();
        $output = $this->makeOutput([], []);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseWhenGotoIsNotEmpty(): void
    {
        $strategy = new WaitStrategy();
        $output = $this->makeOutput([$this->makeTransition()], [$this->makeTransition()]);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsTrueWhenRejectIsAlsoPresent(): void
    {
        $strategy = new WaitStrategy();
        $output = $this->makeOutput([], [$this->makeTransition()], [$this->makeTransition()]);

        $this->assertTrue($strategy->supports($output));
    }

    #[Test]
    public function transitionToNextNodesDoesNotMutatePointerOrCollection(): void
    {
        $strategy = new WaitStrategy();
        $execution = Execution::create();
        $pointer = $execution->pointers->startAt(NodeId::create("state-machine.unit.foundation.transition.strategy.default.waitst.node3"));
        $originalNodeId = $pointer->nodeId->toString();
        $originalStep = $pointer->currentStep;
        $output = $this->makeOutput([], [$this->makeTransition()]);

        $strategy->transitionToNextNodes($execution, $pointer, $output);

        $this->assertSame($originalNodeId, $pointer->nodeId->toString());
        $this->assertSame($originalStep, $pointer->currentStep);
        $this->assertCount(1, $execution->pointers->pointers);
    }
}

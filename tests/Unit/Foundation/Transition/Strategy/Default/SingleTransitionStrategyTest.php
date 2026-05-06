<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Transition\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\SingleTransitionStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SingleTransitionStrategyTest extends TestCase
{
    private function makeOutput(array $goto, array $waitfor = [], array $reject = []): TransitionSelectionOutput
    {
        return new TransitionSelectionOutput($goto, $waitfor, $reject);
    }

    private function makeTransition(?NodeId $to = null): Transition
    {
        return Transition::create(NodeId::new(), $to ?? NodeId::new());
    }

    #[Test]
    public function supportsReturnsTrueForExactlyOneGotoAndNoWaitfor(): void
    {
        $strategy = new SingleTransitionStrategy();
        $output = $this->makeOutput([$this->makeTransition()]);

        $this->assertTrue($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseForEmptyGoto(): void
    {
        $strategy = new SingleTransitionStrategy();
        $output = $this->makeOutput([]);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseForMoreThanOneGoto(): void
    {
        $strategy = new SingleTransitionStrategy();
        $output = $this->makeOutput([$this->makeTransition(), $this->makeTransition()]);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseWhenGotoHasOneButWaitforIsNotEmpty(): void
    {
        $strategy = new SingleTransitionStrategy();
        $output = $this->makeOutput([$this->makeTransition()], [$this->makeTransition()]);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function transitionToNextNodesMovesPointerToTargetNode(): void
    {
        $strategy = new SingleTransitionStrategy();
        $execution = Execution::create();
        $startNodeId = NodeId::new();
        $pointer = $execution->pointers->startAt($startNodeId);
        $targetNodeId = NodeId::new();
        $output = $this->makeOutput([$this->makeTransition($targetNodeId)]);

        $strategy->transitionToNextNodes($execution, $pointer, $output);

        $this->assertTrue($targetNodeId->equals($pointer->nodeId));
    }

    #[Test]
    public function transitionToNextNodesIncreasesPointerStep(): void
    {
        $strategy = new SingleTransitionStrategy();
        $execution = Execution::create();
        $pointer = $execution->pointers->startAt(NodeId::new());
        $output = $this->makeOutput([$this->makeTransition()]);

        $strategy->transitionToNextNodes($execution, $pointer, $output);

        $this->assertSame(1, $pointer->currentStep);
    }
}

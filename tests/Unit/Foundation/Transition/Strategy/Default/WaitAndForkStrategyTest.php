<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Transition\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\WaitAndForkStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WaitAndForkStrategyTest extends TestCase
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
    public function supportsReturnsTrueWhenBothGotoAndWaitforAreNotEmpty(): void
    {
        $strategy = new WaitAndForkStrategy();
        $output = $this->makeOutput([$this->makeTransition()], [$this->makeTransition()]);

        $this->assertTrue($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseWhenOnlyGotoIsPresent(): void
    {
        $strategy = new WaitAndForkStrategy();
        $output = $this->makeOutput([$this->makeTransition()]);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseWhenOnlyWaitforIsPresent(): void
    {
        $strategy = new WaitAndForkStrategy();
        $output = $this->makeOutput([], [$this->makeTransition()]);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseWhenBothAreEmpty(): void
    {
        $strategy = new WaitAndForkStrategy();
        $output = $this->makeOutput([]);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function transitionToNextNodesForksToGotoTargetsButKeepsOriginal(): void
    {
        $strategy = new WaitAndForkStrategy();
        $execution = Execution::create();
        $pointer = $execution->pointers->startAt(NodeId::new());
        $target1 = NodeId::new();
        $target2 = NodeId::new();
        $output = $this->makeOutput(
            [$this->makeTransition($target1), $this->makeTransition($target2)],
            [$this->makeTransition()],
        );

        $strategy->transitionToNextNodes($execution, $pointer, $output);

        $this->assertCount(3, $execution->pointers->pointers);
        $this->assertArrayHasKey($pointer->id->toString(), $execution->pointers->pointers);
    }

    #[Test]
    public function transitionToNextNodesForkedPointersPointToGotoTargetNodes(): void
    {
        $strategy = new WaitAndForkStrategy();
        $execution = Execution::create();
        $pointer = $execution->pointers->startAt(NodeId::new());
        $target = NodeId::new();
        $output = $this->makeOutput(
            [$this->makeTransition($target)],
            [$this->makeTransition()],
        );

        $strategy->transitionToNextNodes($execution, $pointer, $output);

        $forkedPointers = array_filter(
            $execution->pointers->pointers,
            static fn($p) => !$p->id->equals($pointer->id),
        );
        $this->assertCount(1, $forkedPointers);
        $forked = array_values($forkedPointers)[0];
        $this->assertTrue($target->equals($forked->nodeId));
    }

    #[Test]
    public function transitionToNextNodesOriginalPointerIsNotMoved(): void
    {
        $strategy = new WaitAndForkStrategy();
        $execution = Execution::create();
        $startNode = NodeId::new();
        $pointer = $execution->pointers->startAt($startNode);
        $output = $this->makeOutput(
            [$this->makeTransition()],
            [$this->makeTransition()],
        );

        $strategy->transitionToNextNodes($execution, $pointer, $output);

        $this->assertTrue($startNode->equals($pointer->nodeId));
    }
}

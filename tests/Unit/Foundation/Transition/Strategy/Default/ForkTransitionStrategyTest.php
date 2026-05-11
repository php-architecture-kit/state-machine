<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Transition\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\ForkTransitionStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ForkTransitionStrategyTest extends TestCase
{
    private function makeOutput(array $goto, array $waitfor = [], array $reject = []): TransitionSelectionOutput
    {
        return new TransitionSelectionOutput($goto, $waitfor, $reject);
    }

    private function makeTransition(?NodeId $output = null): Transition
    {
        return Transition::create(NodeId::create("state-machine.unit.foundation.transition.strategy.default.forktr.node1"), $output ?? NodeId::create("state-machine.unit.foundation.transition.strategy.default.forktr.node2"));
    }

    #[Test]
    public function supportsReturnsTrueForMoreThanOneGotoAndNoWaitfor(): void
    {
        $strategy = new ForkTransitionStrategy();
        $output = $this->makeOutput([$this->makeTransition(), $this->makeTransition()]);

        $this->assertTrue($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsTrueForThreeGotoTransitionsAndNoWaitfor(): void
    {
        $strategy = new ForkTransitionStrategy();
        $output = $this->makeOutput([
            $this->makeTransition(),
            $this->makeTransition(),
            $this->makeTransition(),
        ]);

        $this->assertTrue($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseForExactlyOneGoto(): void
    {
        $strategy = new ForkTransitionStrategy();
        $output = $this->makeOutput([$this->makeTransition()]);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseForEmptyGoto(): void
    {
        $strategy = new ForkTransitionStrategy();
        $output = $this->makeOutput([]);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function supportsReturnsFalseWhenWaitforIsNotEmpty(): void
    {
        $strategy = new ForkTransitionStrategy();
        $output = $this->makeOutput([$this->makeTransition(), $this->makeTransition()], [$this->makeTransition()]);

        $this->assertFalse($strategy->supports($output));
    }

    #[Test]
    public function transitionToNextNodesCreatesForkedPointerForEachGotoTransition(): void
    {
        $strategy = new ForkTransitionStrategy();
        $execution = Execution::create();
        $pointer = $execution->pointers->startAt(NodeId::create("state-machine.unit.foundation.transition.strategy.default.forktr.node3"));
        $target1 = NodeId::create("state-machine.unit.foundation.transition.strategy.default.forktr.node4");
        $target2 = NodeId::create("state-machine.unit.foundation.transition.strategy.default.forktr.node5");
        $output = $this->makeOutput([
            $this->makeTransition($target1),
            $this->makeTransition($target2),
        ]);

        $strategy->transitionToNextNodes($execution, $pointer, $output);

        $this->assertCount(2, $execution->pointers->pointers);
        $this->assertArrayNotHasKey($pointer->id->toString(), $execution->pointers->pointers);
    }

    #[Test]
    public function transitionToNextNodesForkedPointersAreOnCorrectTargetNodes(): void
    {
        $strategy = new ForkTransitionStrategy();
        $execution = Execution::create();
        $pointer = $execution->pointers->startAt(NodeId::create("state-machine.unit.foundation.transition.strategy.default.forktr.node6"));
        $target1 = NodeId::create("state-machine.unit.foundation.transition.strategy.default.forktr.node7");
        $target2 = NodeId::create("state-machine.unit.foundation.transition.strategy.default.forktr.node8");
        $output = $this->makeOutput([
            $this->makeTransition($target1),
            $this->makeTransition($target2),
        ]);

        $strategy->transitionToNextNodes($execution, $pointer, $output);

        $nodeIds = array_map(
            static fn($p) => $p->nodeId->toString(),
            array_values($execution->pointers->pointers),
        );
        $this->assertContains($target1->toString(), $nodeIds);
        $this->assertContains($target2->toString(), $nodeIds);
    }
}

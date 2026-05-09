<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Transition\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\AllValidTransitionsStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AllValidTransitionsStrategyTest extends TestCase
{
    private function makeCondition(TransitionConditionDecision $decision): TransitionCondition
    {
        return new class($decision) implements TransitionCondition {
            public function __construct(private readonly TransitionConditionDecision $decision) {}

            public function check(States $states): TransitionConditionDecision
            {
                return $this->decision;
            }
        };
    }

    private function makePointer(): Pointer
    {
        return Pointer::create(ExecutionId::new(), NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node1"));
    }

    private function makeStates(): States
    {
        return States::create(ExecutionId::new(), null, null, null);
    }

    #[Test]
    public function unconditionalTransitionGoesToGoto(): void
    {
        $strategy = new AllValidTransitionsStrategy();
        $transition = Transition::create(NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node2"), NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node3"));

        $output = $strategy->select($this->makePointer(), $this->makeStates(), [$transition]);

        $this->assertCount(1, $output->goto);
        $this->assertEmpty($output->waitfor);
        $this->assertEmpty($output->reject);
    }

    #[Test]
    public function acceptedConditionTransitionGoesToGoto(): void
    {
        $strategy = new AllValidTransitionsStrategy();
        $transition = Transition::create(
            NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node4"),
            NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node5"),
            $this->makeCondition(TransitionConditionDecision::Accepted),
        );

        $output = $strategy->select($this->makePointer(), $this->makeStates(), [$transition]);

        $this->assertCount(1, $output->goto);
        $this->assertEmpty($output->waitfor);
        $this->assertEmpty($output->reject);
    }

    #[Test]
    public function waitConditionTransitionGoesToWaitfor(): void
    {
        $strategy = new AllValidTransitionsStrategy();
        $transition = Transition::create(
            NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node6"),
            NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node7"),
            $this->makeCondition(TransitionConditionDecision::Wait),
        );

        $output = $strategy->select($this->makePointer(), $this->makeStates(), [$transition]);

        $this->assertEmpty($output->goto);
        $this->assertCount(1, $output->waitfor);
        $this->assertEmpty($output->reject);
    }

    #[Test]
    public function rejectedConditionTransitionGoesToReject(): void
    {
        $strategy = new AllValidTransitionsStrategy();
        $transition = Transition::create(
            NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node8"),
            NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node9"),
            $this->makeCondition(TransitionConditionDecision::Rejected),
        );

        $output = $strategy->select($this->makePointer(), $this->makeStates(), [$transition]);

        $this->assertEmpty($output->goto);
        $this->assertEmpty($output->waitfor);
        $this->assertCount(1, $output->reject);
    }

    #[Test]
    public function mixedTransitionsAreSortedIntoCorrectBuckets(): void
    {
        $strategy = new AllValidTransitionsStrategy();
        $unconditional = Transition::create(NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node10"), NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node11"));
        $accepted = Transition::create(NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node12"), NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node13"), $this->makeCondition(TransitionConditionDecision::Accepted));
        $waiting = Transition::create(NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node14"), NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node15"), $this->makeCondition(TransitionConditionDecision::Wait));
        $rejected = Transition::create(NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node16"), NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node17"), $this->makeCondition(TransitionConditionDecision::Rejected));

        $output = $strategy->select($this->makePointer(), $this->makeStates(), [
            $unconditional, $accepted, $waiting, $rejected,
        ]);

        $this->assertCount(2, $output->goto);
        $this->assertCount(1, $output->waitfor);
        $this->assertCount(1, $output->reject);
    }

    #[Test]
    public function emptyTransitionsListReturnsEmptyOutput(): void
    {
        $strategy = new AllValidTransitionsStrategy();

        $output = $strategy->select($this->makePointer(), $this->makeStates(), []);

        $this->assertEmpty($output->goto);
        $this->assertEmpty($output->waitfor);
        $this->assertEmpty($output->reject);
    }

    #[Test]
    public function allAcceptedTransitionsGoToGoto(): void
    {
        $strategy = new AllValidTransitionsStrategy();
        $t1 = Transition::create(NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node18"), NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node19"), $this->makeCondition(TransitionConditionDecision::Accepted));
        $t2 = Transition::create(NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node20"), NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node21"), $this->makeCondition(TransitionConditionDecision::Accepted));
        $t3 = Transition::create(NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node22"), NodeId::create("state-machine.unit.foundation.transition.strategy.default.allval.node23"), $this->makeCondition(TransitionConditionDecision::Accepted));

        $output = $strategy->select($this->makePointer(), $this->makeStates(), [$t1, $t2, $t3]);

        $this->assertCount(3, $output->goto);
    }
}

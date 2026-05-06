<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Transition\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\FirstValidTransitionStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FirstValidTransitionStrategyTest extends TestCase
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
        return Pointer::create(ExecutionId::new(), NodeId::new());
    }

    private function makeStates(): States
    {
        return States::create(ExecutionId::new(), null, null, null);
    }

    #[Test]
    public function firstUnconditionalTransitionIsReturnedImmediately(): void
    {
        $strategy = new FirstValidTransitionStrategy();
        $first = Transition::create(NodeId::new(), NodeId::new());
        $second = Transition::create(NodeId::new(), NodeId::new());

        $output = $strategy->select($this->makePointer(), $this->makeStates(), [$first, $second]);

        $this->assertCount(1, $output->goto);
        $this->assertSame($first, $output->goto[0]);
    }

    #[Test]
    public function firstAcceptedConditionTransitionIsReturned(): void
    {
        $strategy = new FirstValidTransitionStrategy();
        $accepted = Transition::create(
            NodeId::new(),
            NodeId::new(),
            $this->makeCondition(TransitionConditionDecision::Accepted),
        );
        $second = Transition::create(NodeId::new(), NodeId::new());

        $output = $strategy->select($this->makePointer(), $this->makeStates(), [$accepted, $second]);

        $this->assertCount(1, $output->goto);
        $this->assertSame($accepted, $output->goto[0]);
    }

    #[Test]
    public function skipsWaitingTransitionsToContinueSearching(): void
    {
        $strategy = new FirstValidTransitionStrategy();
        $waiting = Transition::create(
            NodeId::new(),
            NodeId::new(),
            $this->makeCondition(TransitionConditionDecision::Wait),
        );
        $accepted = Transition::create(
            NodeId::new(),
            NodeId::new(),
            $this->makeCondition(TransitionConditionDecision::Accepted),
        );

        $output = $strategy->select($this->makePointer(), $this->makeStates(), [$waiting, $accepted]);

        $this->assertCount(1, $output->goto);
        $this->assertSame($accepted, $output->goto[0]);
        $this->assertCount(1, $output->waitfor);
    }

    #[Test]
    public function skipsRejectedTransitionsToContinueSearching(): void
    {
        $strategy = new FirstValidTransitionStrategy();
        $rejected = Transition::create(
            NodeId::new(),
            NodeId::new(),
            $this->makeCondition(TransitionConditionDecision::Rejected),
        );
        $accepted = Transition::create(
            NodeId::new(),
            NodeId::new(),
            $this->makeCondition(TransitionConditionDecision::Accepted),
        );

        $output = $strategy->select($this->makePointer(), $this->makeStates(), [$rejected, $accepted]);

        $this->assertCount(1, $output->goto);
        $this->assertSame($accepted, $output->goto[0]);
        $this->assertCount(1, $output->reject);
    }

    #[Test]
    public function allRejectedTransitionsResultInEmptyGoto(): void
    {
        $strategy = new FirstValidTransitionStrategy();
        $r1 = Transition::create(NodeId::new(), NodeId::new(), $this->makeCondition(TransitionConditionDecision::Rejected));
        $r2 = Transition::create(NodeId::new(), NodeId::new(), $this->makeCondition(TransitionConditionDecision::Rejected));

        $output = $strategy->select($this->makePointer(), $this->makeStates(), [$r1, $r2]);

        $this->assertEmpty($output->goto);
        $this->assertCount(2, $output->reject);
    }

    #[Test]
    public function allWaitingTransitionsResultInEmptyGoto(): void
    {
        $strategy = new FirstValidTransitionStrategy();
        $w1 = Transition::create(NodeId::new(), NodeId::new(), $this->makeCondition(TransitionConditionDecision::Wait));
        $w2 = Transition::create(NodeId::new(), NodeId::new(), $this->makeCondition(TransitionConditionDecision::Wait));

        $output = $strategy->select($this->makePointer(), $this->makeStates(), [$w1, $w2]);

        $this->assertEmpty($output->goto);
        $this->assertCount(2, $output->waitfor);
    }

    #[Test]
    public function emptyTransitionsListReturnsEmptyOutput(): void
    {
        $strategy = new FirstValidTransitionStrategy();

        $output = $strategy->select($this->makePointer(), $this->makeStates(), []);

        $this->assertEmpty($output->goto);
        $this->assertEmpty($output->waitfor);
        $this->assertEmpty($output->reject);
    }
}

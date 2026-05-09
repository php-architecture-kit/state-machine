<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Pointer\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointers;
use PhpArchitecture\StateMachine\Foundation\Pointer\Strategy\Default\AllPointersUntilBlockedStrategy;
use PhpArchitecture\StateMachine\Foundation\Pointer\Strategy\PointerExecutionPlan;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AllPointersUntilBlockedStrategyTest extends TestCase
{
    #[Test]
    public function selectReturnsEmptyArrayForEmptyPointers(): void
    {
        $strategy = new AllPointersUntilBlockedStrategy();
        $pointers = Pointers::create(ExecutionId::new(), null, null, null);

        $plans = $strategy->select($pointers);

        $this->assertSame([], $plans);
    }

    #[Test]
    public function selectReturnsOneExecutionPlanPerPointer(): void
    {
        $strategy = new AllPointersUntilBlockedStrategy();
        $executionId = ExecutionId::new();
        $pointers = Pointers::create($executionId, null, null, null);
        $pointers->startAt(NodeId::create("state-machine.unit.foundation.pointer.strategy.default.allpointe.node1"));
        $pointers->startAt(NodeId::create("state-machine.unit.foundation.pointer.strategy.default.allpointe.node2"));

        $plans = $strategy->select($pointers);

        $this->assertCount(2, $plans);
    }

    #[Test]
    public function selectReturnsPlansWithPhpIntMaxSteps(): void
    {
        $strategy = new AllPointersUntilBlockedStrategy();
        $pointers = Pointers::create(ExecutionId::new(), null, null, null);
        $pointers->startAt(NodeId::create("state-machine.unit.foundation.pointer.strategy.default.allpointe.node3"));

        $plans = $strategy->select($pointers);

        $this->assertSame(PHP_INT_MAX, $plans[0]->maxSteps);
    }

    #[Test]
    public function selectReturnsPlansLinkedToCorrectPointers(): void
    {
        $strategy = new AllPointersUntilBlockedStrategy();
        $executionId = ExecutionId::new();
        $pointers = Pointers::create($executionId, null, null, null);
        $pointer = $pointers->startAt(NodeId::create("state-machine.unit.foundation.pointer.strategy.default.allpointe.node4"));

        $plans = $strategy->select($pointers);

        $this->assertSame($pointer, $plans[0]->pointer);
    }

    #[Test]
    public function selectReturnsInstancesOfPointerExecutionPlan(): void
    {
        $strategy = new AllPointersUntilBlockedStrategy();
        $pointers = Pointers::create(ExecutionId::new(), null, null, null);
        $pointers->startAt(NodeId::create("state-machine.unit.foundation.pointer.strategy.default.allpointe.node5"));

        $plans = $strategy->select($pointers);

        $this->assertContainsOnlyInstancesOf(PointerExecutionPlan::class, $plans);
    }
}

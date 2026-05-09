<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Pointer;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\NodeHandlingStatus;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PointerTest extends TestCase
{
    #[Test]
    public function createStoresExecutionIdAndNodeId(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointertest.node1");

        $pointer = Pointer::create($executionId, $nodeId);

        $this->assertTrue($executionId->equals($pointer->executionId));
        $this->assertTrue($nodeId->equals($pointer->nodeId));
    }

    #[Test]
    public function createSetsInitialStepToZero(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::create("state-machine.unit.foundation.pointer.pointertest.node2"));

        $this->assertSame(0, $pointer->currentStep);
    }

    #[Test]
    public function createSetsParentIdToNull(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::create("state-machine.unit.foundation.pointer.pointertest.node3"));

        $this->assertNull($pointer->parentId);
    }

    #[Test]
    public function forkCreatesNewPointerWithSameExecutionIdAndNodeId(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointertest.node4");
        $original = Pointer::create($executionId, $nodeId);

        $forked = $original->fork();

        $this->assertTrue($executionId->equals($forked->executionId));
        $this->assertTrue($nodeId->equals($forked->nodeId));
    }

    #[Test]
    public function forkCreatesPointerWithDifferentId(): void
    {
        $original = Pointer::create(ExecutionId::new(), NodeId::create("state-machine.unit.foundation.pointer.pointertest.node5"));

        $forked = $original->fork();

        $this->assertFalse($original->id->equals($forked->id));
    }

    #[Test]
    public function forkSetsParentIdToOriginalId(): void
    {
        $original = Pointer::create(ExecutionId::new(), NodeId::create("state-machine.unit.foundation.pointer.pointertest.node6"));

        $forked = $original->fork();

        $this->assertTrue($original->id->equals($forked->parentId));
    }

    #[Test]
    public function forkInheritsCurrentStep(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::create("state-machine.unit.foundation.pointer.pointertest.node7");
        $original = Pointer::create($executionId, $nodeId);
        $original->step(NodeId::create("state-machine.unit.foundation.pointer.pointertest.node8"));
        $original->step(NodeId::create("state-machine.unit.foundation.pointer.pointertest.node9"));

        $forked = $original->fork();

        $this->assertSame(2, $forked->currentStep);
    }

    #[Test]
    public function stepIncrementsCurrentStepByOne(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::create("state-machine.unit.foundation.pointer.pointertest.node10"));

        $pointer->step(NodeId::create("state-machine.unit.foundation.pointer.pointertest.node11"));

        $this->assertSame(1, $pointer->currentStep);
    }

    #[Test]
    public function stepUpdatesNodeId(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::create("state-machine.unit.foundation.pointer.pointertest.node12"));
        $nextNodeId = NodeId::create("state-machine.unit.foundation.pointer.pointertest.node13");

        $pointer->step($nextNodeId);

        $this->assertTrue($nextNodeId->equals($pointer->nodeId));
    }

    #[Test]
    public function multipleStepsAccumulate(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::create("state-machine.unit.foundation.pointer.pointertest.node14"));

        $pointer->step(NodeId::create("state-machine.unit.foundation.pointer.pointertest.node15"));
        $pointer->step(NodeId::create("state-machine.unit.foundation.pointer.pointertest.node16"));
        $pointer->step(NodeId::create("state-machine.unit.foundation.pointer.pointertest.node17"));

        $this->assertSame(3, $pointer->currentStep);
    }

    #[Test]
    public function createSetsPendingHandlingStatus(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::create("state-machine.unit.foundation.pointer.pointertest.node18"));

        $this->assertSame(NodeHandlingStatus::Pending, $pointer->handlingStatus);
    }

    #[Test]
    public function markNodeHandlingStatusCompletedSetsCompletedStatus(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::create("state-machine.unit.foundation.pointer.pointertest.node19"));

        $pointer->markNodeHandlingStatusCompleted();

        $this->assertSame(NodeHandlingStatus::Completed, $pointer->handlingStatus);
    }

    #[Test]
    public function stepResetsHandlingStatusToPending(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::create("state-machine.unit.foundation.pointer.pointertest.node20"));
        $pointer->markNodeHandlingStatusCompleted();

        $pointer->step(NodeId::create("state-machine.unit.foundation.pointer.pointertest.node21"));

        $this->assertSame(NodeHandlingStatus::Pending, $pointer->handlingStatus);
    }

    #[Test]
    public function forkAlwaysSetsPendingHandlingStatusRegardlessOfParentStatus(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::create("state-machine.unit.foundation.pointer.pointertest.node22"));
        $pointer->markNodeHandlingStatusCompleted();

        $forked = $pointer->fork();

        $this->assertSame(NodeHandlingStatus::Pending, $forked->handlingStatus);
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Pointer;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PointerTest extends TestCase
{
    #[Test]
    public function createStoresExecutionIdAndNodeId(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::new();

        $pointer = Pointer::create($executionId, $nodeId);

        $this->assertTrue($executionId->equals($pointer->executionId));
        $this->assertTrue($nodeId->equals($pointer->nodeId));
    }

    #[Test]
    public function createSetsInitialStepToZero(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::new());

        $this->assertSame(0, $pointer->currentStep);
    }

    #[Test]
    public function createSetsParentIdToNull(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::new());

        $this->assertNull($pointer->parentId);
    }

    #[Test]
    public function forkCreatesNewPointerWithSameExecutionIdAndNodeId(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::new();
        $original = Pointer::create($executionId, $nodeId);

        $forked = $original->fork();

        $this->assertTrue($executionId->equals($forked->executionId));
        $this->assertTrue($nodeId->equals($forked->nodeId));
    }

    #[Test]
    public function forkCreatesPointerWithDifferentId(): void
    {
        $original = Pointer::create(ExecutionId::new(), NodeId::new());

        $forked = $original->fork();

        $this->assertFalse($original->id->equals($forked->id));
    }

    #[Test]
    public function forkSetsParentIdToOriginalId(): void
    {
        $original = Pointer::create(ExecutionId::new(), NodeId::new());

        $forked = $original->fork();

        $this->assertTrue($original->id->equals($forked->parentId));
    }

    #[Test]
    public function forkInheritsCurrentStep(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::new();
        $original = Pointer::create($executionId, $nodeId);
        $original->step(NodeId::new());
        $original->step(NodeId::new());

        $forked = $original->fork();

        $this->assertSame(2, $forked->currentStep);
    }

    #[Test]
    public function stepIncrementsCurrentStepByOne(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::new());

        $pointer->step(NodeId::new());

        $this->assertSame(1, $pointer->currentStep);
    }

    #[Test]
    public function stepUpdatesNodeId(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::new());
        $nextNodeId = NodeId::new();

        $pointer->step($nextNodeId);

        $this->assertTrue($nextNodeId->equals($pointer->nodeId));
    }

    #[Test]
    public function multipleStepsAccumulate(): void
    {
        $pointer = Pointer::create(ExecutionId::new(), NodeId::new());

        $pointer->step(NodeId::new());
        $pointer->step(NodeId::new());
        $pointer->step(NodeId::new());

        $this->assertSame(3, $pointer->currentStep);
    }
}

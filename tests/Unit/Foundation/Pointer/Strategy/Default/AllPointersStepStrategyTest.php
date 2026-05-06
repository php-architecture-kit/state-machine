<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Pointer\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointers;
use PhpArchitecture\StateMachine\Foundation\Pointer\Strategy\Default\AllPointersStepStrategy;
use PhpArchitecture\StateMachine\Foundation\Pointer\Strategy\PointerExecutionPlan;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AllPointersStepStrategyTest extends TestCase
{
    #[Test]
    public function selectReturnsEmptyArrayForEmptyPointers(): void
    {
        $strategy = new AllPointersStepStrategy();
        $pointers = Pointers::create(ExecutionId::new(), null, null, null);

        $plans = $strategy->select($pointers);

        $this->assertSame([], $plans);
    }

    #[Test]
    public function selectReturnsOneExecutionPlanPerPointer(): void
    {
        $strategy = new AllPointersStepStrategy();
        $pointers = Pointers::create(ExecutionId::new(), null, null, null);
        $pointers->startAt(NodeId::new());
        $pointers->startAt(NodeId::new());

        $plans = $strategy->select($pointers);

        $this->assertCount(2, $plans);
    }

    #[Test]
    public function selectReturnsPlansWithMaxStepsOne(): void
    {
        $strategy = new AllPointersStepStrategy();
        $pointers = Pointers::create(ExecutionId::new(), null, null, null);
        $pointers->startAt(NodeId::new());

        $plans = $strategy->select($pointers);

        $this->assertSame(1, $plans[0]->maxSteps);
    }

    #[Test]
    public function selectReturnsPlansLinkedToCorrectPointers(): void
    {
        $strategy = new AllPointersStepStrategy();
        $pointers = Pointers::create(ExecutionId::new(), null, null, null);
        $pointer = $pointers->startAt(NodeId::new());

        $plans = $strategy->select($pointers);

        $this->assertSame($pointer, $plans[0]->pointer);
    }

    #[Test]
    public function selectReturnsInstancesOfPointerExecutionPlan(): void
    {
        $strategy = new AllPointersStepStrategy();
        $pointers = Pointers::create(ExecutionId::new(), null, null, null);
        $pointers->startAt(NodeId::new());

        $plans = $strategy->select($pointers);

        $this->assertContainsOnlyInstancesOf(PointerExecutionPlan::class, $plans);
    }
}

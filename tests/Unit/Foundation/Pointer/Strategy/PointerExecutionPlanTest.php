<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Pointer\Strategy;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\Pointer\Strategy\PointerExecutionPlan;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PointerExecutionPlanTest extends TestCase
{
    private function makePointer(): Pointer
    {
        return Pointer::create(ExecutionId::new(), NodeId::new());
    }

    #[Test]
    public function stepCreatesExecutionPlanWithMaxStepsOne(): void
    {
        $pointer = $this->makePointer();

        $plan = PointerExecutionPlan::step($pointer);

        $this->assertSame($pointer, $plan->pointer);
        $this->assertSame(1, $plan->maxSteps);
    }

    #[Test]
    public function untilBlockedCreatesExecutionPlanWithMaxStepsPhpIntMax(): void
    {
        $pointer = $this->makePointer();

        $plan = PointerExecutionPlan::untilBlocked($pointer);

        $this->assertSame($pointer, $plan->pointer);
        $this->assertSame(PHP_INT_MAX, $plan->maxSteps);
    }

    #[Test]
    public function maxStepsCreatesExecutionPlanWithSpecifiedMaxSteps(): void
    {
        $pointer = $this->makePointer();

        $plan = PointerExecutionPlan::maxSteps($pointer, 5);

        $this->assertSame($pointer, $plan->pointer);
        $this->assertSame(5, $plan->maxSteps);
    }

    #[Test]
    public function constructorStoresPointerAndMaxSteps(): void
    {
        $pointer = $this->makePointer();

        $plan = new PointerExecutionPlan($pointer, 42);

        $this->assertSame($pointer, $plan->pointer);
        $this->assertSame(42, $plan->maxSteps);
    }
}

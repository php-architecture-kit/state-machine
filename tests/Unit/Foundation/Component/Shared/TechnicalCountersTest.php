<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\Shared;

use PhpArchitecture\StateMachine\Foundation\Component\Shared\TechnicalCounters;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TechnicalCountersTest extends TestCase
{
    #[Test]
    public function missingCounterIsZero(): void
    {
        $this->assertSame(0, TechnicalCounters::get($this->states(), 'x'));
    }

    #[Test]
    public function changesSeveralCountersAtOnce(): void
    {
        $states = $this->states();

        TechnicalCounters::change($states, ['x' => 2, 'y' => 1]);
        TechnicalCounters::change($states, ['x' => -1]);

        $this->assertSame(1, TechnicalCounters::get($states, 'x'));
        $this->assertSame(1, TechnicalCounters::get($states, 'y'));
    }

    #[Test]
    public function counterAtZeroIsRemovedFromTheState(): void
    {
        $states = $this->states();

        TechnicalCounters::change($states, ['x' => 1]);
        TechnicalCounters::change($states, ['x' => -1]);

        $this->assertSame([], $states->getTechnicalState()->details);
    }

    #[Test]
    public function nonIntegerValueCountsAsZero(): void
    {
        $states = $this->states();
        $states->modifyState($states->getTechnicalState()->id, ['x' => true]);

        $this->assertSame(0, TechnicalCounters::get($states, 'x'));
    }

    private function states(): States
    {
        return States::create(ExecutionId::new(), null, null, null);
    }
}

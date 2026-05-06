<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Transition\Strategy\Output;

use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Transition\Exception\InvalidTransitionException;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class TransitionSelectionOutputTest extends TestCase
{
    private function makeTransition(): Transition
    {
        return Transition::create(NodeId::new(), NodeId::new());
    }

    #[Test]
    public function constructorStoresGotoWaitforAndRejectArrays(): void
    {
        $goto = [$this->makeTransition()];
        $waitfor = [$this->makeTransition()];
        $reject = [$this->makeTransition()];

        $output = new TransitionSelectionOutput($goto, $waitfor, $reject);

        $this->assertSame($goto, $output->goto);
        $this->assertSame($waitfor, $output->waitfor);
        $this->assertSame($reject, $output->reject);
    }

    #[Test]
    public function constructorAcceptsAllEmptyArrays(): void
    {
        $output = new TransitionSelectionOutput([], [], []);

        $this->assertEmpty($output->goto);
        $this->assertEmpty($output->waitfor);
        $this->assertEmpty($output->reject);
    }

    #[Test]
    public function constructorThrowsInvalidTransitionExceptionWhenGotoContainsNonTransition(): void
    {
        $this->expectException(InvalidTransitionException::class);

        new TransitionSelectionOutput([new stdClass()], [], []);
    }

    #[Test]
    public function constructorThrowsInvalidTransitionExceptionWhenWaitforContainsNonTransition(): void
    {
        $this->expectException(InvalidTransitionException::class);

        new TransitionSelectionOutput([], [new stdClass()], []);
    }

    #[Test]
    public function constructorThrowsInvalidTransitionExceptionWhenRejectContainsNonTransition(): void
    {
        $this->expectException(InvalidTransitionException::class);

        new TransitionSelectionOutput([], [], [new stdClass()]);
    }
}

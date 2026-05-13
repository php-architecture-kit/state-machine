<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\State;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateDefinedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateModifiedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateRemovedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Modification\CannotModifyStateException;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Removal\CannotRemoveStateException;
use PhpArchitecture\StateMachine\Foundation\State\Identity\StateId;
use PhpArchitecture\StateMachine\Foundation\State\Policy\StateDefinitionPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\StateModificationPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\StateRemovalPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StatesTest extends TestCase
{
    private function makeStates(?ExecutionId $executionId = null): States
    {
        return States::create($executionId ?? ExecutionId::new(), null, null, null);
    }

    #[Test]
    public function createReturnsEmptyStates(): void
    {
        $states = $this->makeStates();

        $this->assertEmpty($states->states);
    }

    #[Test]
    public function createStoresExecutionId(): void
    {
        $executionId = ExecutionId::new();
        $states = $this->makeStates($executionId);

        $this->assertTrue($executionId->equals($states->executionId));
    }

    #[Test]
    public function recreateWrapsProvidedStates(): void
    {
        $executionId = ExecutionId::new();
        $state = State::create($executionId, 'order', []);
        $states = States::recreate($executionId, null, null, null, [], [$state]);

        $this->assertArrayHasKey($state->id->toString(), $states->states);
    }

    #[Test]
    public function defineStateCreatesStateAndAddsItToCollection(): void
    {
        $states = $this->makeStates();

        $state = $states->defineState('order', []);

        $this->assertCount(1, $states->states);
        $this->assertArrayHasKey($state->id->toString(), $states->states);
    }

    #[Test]
    public function defineStateRecordsStateDefinedEvent(): void
    {
        $states = $this->makeStates();

        $states->defineState('order', []);

        $events = $states->getEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(StateDefinedEvent::class, $events[0]);
    }

    #[Test]
    public function defineStateCallsDefinitionPolicyWhenSet(): void
    {
        $executionId = ExecutionId::new();
        $policyCalled = false;
        $policy = new class($policyCalled) implements StateDefinitionPolicy {
            public function __construct(private bool &$called) {}
            public function assertStateDefinitionAllowed(State $state, States $states): void
            {
                $this->called = true;
            }
        };
        $states = States::create($executionId, $policy, null, null);

        $states->defineState('order', []);

        $this->assertTrue($policyCalled);
    }

    #[Test]
    public function modifyStateAddsNewDetailToState(): void
    {
        $states = $this->makeStates();
        $state = $states->defineState('order', []);
        $states->releaseEvents();

        $states->modifyState($state->id, [new StateDetail('amount', 100)], []);

        $updatedState = $states->states[$state->id->toString()];
        $this->assertArrayHasKey('amount', $updatedState->details);
    }

    #[Test]
    public function modifyStateRemovesExistingDetailFromState(): void
    {
        $states = $this->makeStates();
        $state = $states->defineState('order', [new StateDetail('amount', 100)]);
        $states->releaseEvents();

        $states->modifyState($state->id, [], ['amount']);

        $updatedState = $states->states[$state->id->toString()];
        $this->assertArrayNotHasKey('amount', $updatedState->details);
    }

    #[Test]
    public function modifyStateRecordsStateModifiedEvent(): void
    {
        $states = $this->makeStates();
        $state = $states->defineState('order', []);
        $states->releaseEvents();

        $states->modifyState($state->id, [new StateDetail('amount', 100)], []);

        $events = $states->getEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(StateModifiedEvent::class, $events[0]);
    }

    #[Test]
    public function modifyStateThrowsCannotModifyStateExceptionForNonexistentState(): void
    {
        $states = $this->makeStates();

        $this->expectException(CannotModifyStateException::class);

        $states->modifyState(StateId::new(), [], []);
    }

    #[Test]
    public function modifyStateCallsModificationPolicyWhenSet(): void
    {
        $executionId = ExecutionId::new();
        $policyCalled = false;
        $policy = new class($policyCalled) implements StateModificationPolicy {
            public function __construct(private bool &$called) {}
            public function assertStateModificationAllowed(State $before, State $after, States $states): void
            {
                $this->called = true;
            }
        };
        $states = States::create($executionId, null, $policy, null);
        $state = $states->defineState('order', []);

        $states->modifyState($state->id, [new StateDetail('amount', 1)], []);

        $this->assertTrue($policyCalled);
    }

    #[Test]
    public function modifyStateReplacesExistingDetailWhenAddingWithSameName(): void
    {
        $states = $this->makeStates();
        $state = $states->defineState('order', [new StateDetail('amount', 10)]);

        $states->modifyState($state->id, [new StateDetail('amount', 99)], []);

        $updatedState = $states->states[$state->id->toString()];
        $this->assertSame(99, $updatedState->details['amount']->value);
    }

    #[Test]
    public function removeStateDeletesStateFromCollection(): void
    {
        $states = $this->makeStates();
        $state = $states->defineState('order', []);
        $states->releaseEvents();

        $states->removeState($state->id);

        $this->assertEmpty($states->states);
    }

    #[Test]
    public function removeStateRecordsStateRemovedEvent(): void
    {
        $states = $this->makeStates();
        $state = $states->defineState('order', []);
        $states->releaseEvents();

        $states->removeState($state->id);

        $events = $states->getEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(StateRemovedEvent::class, $events[0]);
    }

    #[Test]
    public function removeStateThrowsCannotRemoveStateExceptionForNonexistentState(): void
    {
        $states = $this->makeStates();

        $this->expectException(CannotRemoveStateException::class);

        $states->removeState(StateId::new());
    }

    #[Test]
    public function removeStateCallsRemovalPolicyWhenSet(): void
    {
        $executionId = ExecutionId::new();
        $policyCalled = false;
        $policy = new class($policyCalled) implements StateRemovalPolicy {
            public function __construct(private bool &$called) {}
            public function assertStateRemovalAllowed(State $state, States $states): void
            {
                $this->called = true;
            }
        };
        $states = States::create($executionId, null, null, $policy);
        $state = $states->defineState('order', []);

        $states->removeState($state->id);

        $this->assertTrue($policyCalled);
    }
}

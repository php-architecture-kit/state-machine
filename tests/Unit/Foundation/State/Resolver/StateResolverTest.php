<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\State\Resolver;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\State\Resolver\StateResolverInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StateResolverTest extends TestCase
{
    private function makeStates(array $resolvers = []): States
    {
        return States::create(ExecutionId::new(), null, null, null, $resolvers);
    }

    private function makeResolver(bool $supports, ?State $resolved = null): StateResolverInterface
    {
        return new class($supports, $resolved) implements StateResolverInterface {
            public function __construct(
                private readonly bool $supports,
                private readonly ?State $resolved,
            ) {}

            public function supports(States $states, string $stateName): bool
            {
                return $this->supports;
            }

            public function resolve(States $states, string $stateName): State
            {
                return $this->resolved;
            }
        };
    }

    #[Test]
    public function getStateReturnsNullWhenNoResolverSupportsAndStateNotDefined(): void
    {
        $resolver = $this->makeResolver(false);
        $states = $this->makeStates([$resolver]);

        $result = $states->getState('unknown');

        $this->assertNull($result);
    }

    #[Test]
    public function getStateReturnsNullWhenNoResolversRegisteredAndStateNotDefined(): void
    {
        $states = $this->makeStates([]);

        $result = $states->getState('unknown');

        $this->assertNull($result);
    }

    #[Test]
    public function getStateCallsResolverWhenStateNotFoundInCollection(): void
    {
        $executionId = ExecutionId::new();
        $expected = State::create($executionId, 'virtual', []);
        $resolver = $this->makeResolver(true, $expected);
        $states = $this->makeStates([$resolver]);

        $result = $states->getState('virtual');

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function getStateDoesNotCallResolverWhenStateExistsInCollection(): void
    {
        $resolverCalled = false;
        $resolver = new class($resolverCalled) implements StateResolverInterface {
            public function __construct(private bool &$called) {}

            public function supports(States $states, string $stateName): bool
            {
                $this->called = true;
                return true;
            }

            public function resolve(States $states, string $stateName): State
            {
                return State::create($states->executionId, $stateName, []);
            }
        };

        $states = $this->makeStates([$resolver]);
        $states->defineState('order', []);

        $states->getState('order');

        $this->assertFalse($resolverCalled);
    }

    #[Test]
    public function getStateSkipsResolversThatDoNotSupport(): void
    {
        $executionId = ExecutionId::new();
        $expected = State::create($executionId, 'virtual', []);

        $notSupporting = $this->makeResolver(false);
        $supporting = $this->makeResolver(true, $expected);

        $states = $this->makeStates([$notSupporting, $supporting]);

        $result = $states->getState('virtual');

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function getStateUsesFirstSupportingResolverAndIgnoresSubsequentOnes(): void
    {
        $executionId = ExecutionId::new();
        $firstState = State::create($executionId, 'first', []);
        $secondState = State::create($executionId, 'second', []);

        $first = $this->makeResolver(true, $firstState);
        $second = $this->makeResolver(true, $secondState);

        $states = $this->makeStates([$first, $second]);

        $result = $states->getState('virtual');

        $this->assertSame($firstState, $result);
    }

    #[Test]
    public function getStateDoesNotCallResolverForReservedTechnicalStateName(): void
    {
        $resolverCalled = false;
        $resolver = new class($resolverCalled) implements StateResolverInterface {
            public function __construct(private bool &$called) {}

            public function supports(States $states, string $stateName): bool
            {
                $this->called = true;
                return true;
            }

            public function resolve(States $states, string $stateName): State
            {
                return State::create($states->executionId, $stateName, []);
            }
        };

        $states = $this->makeStates([$resolver]);

        $states->getState(State::Technical);

        $this->assertFalse($resolverCalled);
    }

    #[Test]
    public function recreateAcceptsResolversAndUsesThemOnGetState(): void
    {
        $executionId = ExecutionId::new();
        $expected = State::create($executionId, 'computed', []);
        $resolver = $this->makeResolver(true, $expected);

        $states = States::recreate($executionId, null, null, null, [$resolver], []);

        $result = $states->getState('computed');

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function supportsIsCalledWithCorrectArguments(): void
    {
        $capturedStates = null;
        $capturedName = null;
        $executionId = ExecutionId::new();

        $resolver = new class($capturedStates, $capturedName) implements StateResolverInterface {
            public function __construct(
                private mixed &$capturedStates,
                private mixed &$capturedName,
            ) {}

            public function supports(States $states, string $stateName): bool
            {
                $this->capturedStates = $states;
                $this->capturedName = $stateName;
                return false;
            }

            public function resolve(States $states, string $stateName): State
            {
                return State::create($states->executionId, $stateName, []);
            }
        };

        $states = States::create($executionId, null, null, null, [$resolver]);

        $states->getState('my-state');

        $this->assertSame($states, $capturedStates);
        $this->assertSame('my-state', $capturedName);
    }

    #[Test]
    public function resolveIsCalledWithCorrectArgumentsWhenSupported(): void
    {
        $capturedStates = null;
        $capturedName = null;
        $executionId = ExecutionId::new();
        $returnedState = State::create($executionId, 'target', []);

        $resolver = new class($capturedStates, $capturedName, $returnedState) implements StateResolverInterface {
            public function __construct(
                private mixed &$capturedStates,
                private mixed &$capturedName,
                private readonly State $returnedState,
            ) {}

            public function supports(States $states, string $stateName): bool
            {
                return true;
            }

            public function resolve(States $states, string $stateName): State
            {
                $this->capturedStates = $states;
                $this->capturedName = $stateName;
                return $this->returnedState;
            }
        };

        $states = States::create($executionId, null, null, null, [$resolver]);

        $states->getState('target');

        $this->assertSame($states, $capturedStates);
        $this->assertSame('target', $capturedName);
    }
}

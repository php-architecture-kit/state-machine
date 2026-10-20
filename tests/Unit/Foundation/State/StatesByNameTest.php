<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\State;

use DateTimeImmutable;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateDefinedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateModifiedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateRemovedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Policy\StateDefinitionPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\StateModificationPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\StateRemovalPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\StateMachine\Foundation\State\Resolver\StateResolverInterface;
use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StatesByNameTest extends TestCase
{
    #[Test]
    public function defineOrModifyStateDefinesAMissingState(): void
    {
        $states = $this->makeStates();

        $state = $states->defineOrModifyState('car', ['floor' => 0]);

        $this->assertSame(['floor' => 0], $state->values());
        $this->assertSame($state, $states->getState('car'));
        $this->assertEventTypes([StateDefinedEvent::class], $states);
    }

    #[Test]
    public function defineOrModifyStateSetsOnlyChangedDetailsAndKeepsTheOthers(): void
    {
        $states = $this->makeStates();
        $states->defineState('car', [new StateDetail('floor', 0), new StateDetail('doors', 'closed')]);
        $states->releaseEvents();

        $state = $states->defineOrModifyState('car', ['floor' => 3, 'doors' => 'closed', 'load' => 2]);

        $this->assertEquals(['floor' => 3, 'doors' => 'closed', 'load' => 2], $state->values());
        $events = $states->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(StateModifiedEvent::class, $events[0]);
        $this->assertSame(['floor', 'load'], array_keys($events[0]->addedDetails));
    }

    #[Test]
    public function defineOrModifyStateRecordsNothingWhenNothingChanges(): void
    {
        $policy = new class implements StateModificationPolicy {
            public int $calls = 0;
            public function assertStateModificationAllowed(State $original, State $changed, States $states): void
            {
                $this->calls++;
            }
        };
        $states = States::create(ExecutionId::new(), null, $policy, null);
        $defined = $states->defineState('car', [new StateDetail('floor', 3), new StateDetail('stops', [1, 2])]);
        $states->releaseEvents();

        $state = $states->defineOrModifyState('car', ['floor' => 3, 'stops' => [1, 2]]);

        $this->assertSame($defined, $state);
        $this->assertSame([], $states->getEvents());
        $this->assertSame(0, $policy->calls);
    }

    #[Test]
    public function defineOrModifyStateGoesThroughTheModificationPolicyWhenSomethingChanges(): void
    {
        $policy = new class implements StateModificationPolicy {
            public int $calls = 0;
            public function assertStateModificationAllowed(State $original, State $changed, States $states): void
            {
                $this->calls++;
            }
        };
        $states = States::create(ExecutionId::new(), null, $policy, null);
        $states->defineState('car', [new StateDetail('floor', 0)]);

        $states->defineOrModifyState('car', ['floor' => 1]);

        $this->assertSame(1, $policy->calls);
    }

    #[Test]
    public function defineOrModifyStatePassesStateDetailsToDefineState(): void
    {
        $states = DefineStateSpy::create(ExecutionId::new(), null, null, null);

        $states->defineOrModifyState('car', ['floor' => 0, new StateDetail('doors', 'closed')]);

        $this->assertEquals(['floor' => new StateDetail('floor', 0), 'doors' => new StateDetail('doors', 'closed')], $states->defined);
    }

    #[Test]
    public function defineOrModifyStateAcceptsStateDetails(): void
    {
        $states = $this->makeStates();
        $states->defineState('car', [new StateDetail('floor', 0)]);
        $states->releaseEvents();

        $states->defineOrModifyState('car', [new StateDetail('floor', 0), new StateDetail('load', 1)]);

        $this->assertSame(['floor' => 0, 'load' => 1], $states->getState('car')?->values());
        $event = $states->releaseEvents()[0];
        $this->assertInstanceOf(StateModifiedEvent::class, $event);
        $this->assertSame(['load'], array_keys($event->addedDetails));
    }

    #[Test]
    public function anEqualObjectCountsAsChangedSoNoChangeIsLost(): void
    {
        $states = $this->makeStates();
        $states->defineState('timer', [new StateDetail('at', new DateTimeImmutable('2026-01-01 12:00 UTC'))]);
        $states->releaseEvents();

        // == would call these equal (same instant) and drop the timezone change
        $later = new DateTimeImmutable('2026-01-01 13:00 +01:00');
        $states->defineOrModifyState('timer', ['at' => $later]);

        $this->assertSame($later, $states->getState('timer')?->value('at'));
        $this->assertCount(1, $states->getEvents());
    }

    #[Test]
    public function defineOrModifyStateTargetsTheFirstStateWithTheName(): void
    {
        $states = $this->makeStates();
        $first = $states->defineState('car', [new StateDetail('floor', 0)]);
        $second = $states->defineState('car', [new StateDetail('floor', 0)]);

        $states->defineOrModifyState('car', ['floor' => 5]);

        $this->assertSame(5, $states->states[$first->id->toString()]->value('floor'));
        $this->assertSame(0, $states->states[$second->id->toString()]->value('floor'));
    }

    #[Test]
    public function defineOrModifyStateDefinesAStoredStateInsteadOfWritingToAResolvedOne(): void
    {
        $states = $this->makeStates([$this->resolverOf('rates', ['eur' => 4.3, 'usd' => 3.9])]);

        $states->defineOrModifyState('rates', ['eur' => 4.2]);

        $this->assertSame(['eur' => 4.2], $states->getState('rates')?->values());
        $this->assertCount(1, $states->states);
    }

    #[Test]
    public function defineOrModifyStateGoesThroughTheDefinitionPolicy(): void
    {
        $policy = new class implements StateDefinitionPolicy {
            public int $calls = 0;
            public function assertStateDefinitionAllowed(State $state, States $states): void
            {
                $this->calls++;
            }
        };
        $states = States::create(ExecutionId::new(), $policy, null, null);

        $states->defineOrModifyState('car', ['floor' => 0]);

        $this->assertSame(1, $policy->calls);
    }

    #[Test]
    public function defineOrModifyStateWritesTheTechnicalState(): void
    {
        $states = $this->makeStates();

        $states->defineOrModifyState(State::Technical, ['counter' => 1]);

        $this->assertSame(1, $states->getTechnicalState()->value('counter'));
        $this->assertCount(1, $states->states);
    }

    #[Test]
    public function removeStateByNameRemovesTheStoredState(): void
    {
        $policy = new class implements StateRemovalPolicy {
            public int $calls = 0;
            public function assertStateRemovalAllowed(State $state, States $states): void
            {
                $this->calls++;
            }
        };
        $states = States::create(ExecutionId::new(), null, null, $policy);
        $states->defineState('car', []);
        $states->releaseEvents();

        $states->removeStateByName('car');

        $this->assertNull($states->getState('car'));
        $this->assertSame(1, $policy->calls);
        $this->assertEventTypes([StateRemovedEvent::class], $states);
    }

    #[Test]
    public function removeStateByNameDoesNothingWithoutAStoredState(): void
    {
        $states = $this->makeStates([$this->resolverOf('rates', ['eur' => 4.3])]);

        $states->removeStateByName('car');
        $states->removeStateByName('rates');

        $this->assertSame([], $states->getEvents());
        $this->assertNotNull($states->getState('rates'));
    }

    /** @param StateResolverInterface[] $resolvers */
    private function makeStates(array $resolvers = []): States
    {
        return States::create(ExecutionId::new(), null, null, null, $resolvers);
    }

    /** @param array<string,mixed> $values */
    private function resolverOf(string $name, array $values): StateResolverInterface
    {
        return new class($name, $values) implements StateResolverInterface {
            /** @param array<string,mixed> $values */
            public function __construct(private readonly string $name, private readonly array $values) {}

            public function supports(States $states, string $stateName): bool
            {
                return $stateName === $this->name;
            }

            public function resolve(States $states, string $stateName): State
            {
                return State::create($states->executionId, $stateName, $this->values);
            }
        };
    }

    /** @param class-string[] $expected */
    private function assertEventTypes(array $expected, States $states): void
    {
        $this->assertSame($expected, array_map(static fn(object $event): string => $event::class, $states->getEvents()));
    }
}

final class DefineStateSpy extends States
{
    /** @var StateDetail[] */
    public array $defined = [];

    public function defineState(string $name, array $details): State
    {
        $this->defined = $details;

        return parent::defineState($name, $details);
    }
}

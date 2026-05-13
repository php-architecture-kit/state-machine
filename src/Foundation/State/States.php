<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State;

use PhpArchitecture\DomainCore\AggregateRoot;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateDefinedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateModifiedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Event\StateRemovedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Definition\StateDefinitionException;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Modification\CannotModifyStateException;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Modification\StateModificationException;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Removal\CannotRemoveStateException;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Removal\StateRemovalException;
use PhpArchitecture\StateMachine\Foundation\State\Identity\StateId;
use PhpArchitecture\StateMachine\Foundation\State\Policy\StateDefinitionPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\StateModificationPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\StateRemovalPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\StateMachine\Foundation\State\Resolver\StateResolverInterface;
use PhpArchitecture\Technical\ArrayTransformation;
use PhpArchitecture\Technical\Assert;

class States extends AggregateRoot
{
    public const RESERVED_STATE_NAMES = [State::Technical];

    /**
     * @param StateResolverInterface[] $resolvers
     * @param State[] $states
     */
    protected function __construct(
        public readonly ExecutionId $executionId,
        public readonly ?StateDefinitionPolicy $definitionPolicy,
        public readonly ?StateModificationPolicy $modificationPolicy,
        public readonly ?StateRemovalPolicy $removalPolicy,
        public readonly array $resolvers,
        public protected(set) array $states,
    ) {
        Assert::eachInstanceOf($resolvers, StateResolverInterface::class);
        Assert::eachInstanceOf($states, State::class);
        $this->states = ArrayTransformation::indexBy($states, static fn(State $state) => $state->id->toString());
    }

    /**
     * @param StateResolverInterface[] $resolvers
     */
    public static function create(
        ExecutionId $executionId,
        ?StateDefinitionPolicy $definitionPolicy,
        ?StateModificationPolicy $modificationPolicy,
        ?StateRemovalPolicy $removalPolicy,
        array $resolvers = [],
    ): static {
        /** @phpstan-ignore-next-line */
        return new static($executionId, $definitionPolicy, $modificationPolicy, $removalPolicy, $resolvers, []);
    }

    /**
     * @param StateResolverInterface[] $resolvers
     * @param State[] $states
     */
    public static function recreate(
        ExecutionId $executionId,
        ?StateDefinitionPolicy $definitionPolicy,
        ?StateModificationPolicy $modificationPolicy,
        ?StateRemovalPolicy $removalPolicy,
        array $resolvers,
        array $states,
    ): static {
        /** @phpstan-ignore-next-line */
        return new static($executionId, $definitionPolicy, $modificationPolicy, $removalPolicy, $resolvers, $states);
    }

    /**
     * @param StateDetail[] $details
     * @throws StateDefinitionException
     */
    public function defineState(string $name, array $details): State
    {
        $state = State::create($this->executionId, $name, $details);

        if ($this->definitionPolicy !== null) {
            $this->definitionPolicy->assertStateDefinitionAllowed($state, $this);
        }

        $this->states[$state->id->toString()] = $state;
        $this->recordEvent(new StateDefinedEvent($state->id, $state->name, $state->details));

        return $state;
    }

    public function getState(string $name): ?State
    {
        foreach ($this->states as $state) {
            if ($state->name === $name) {
                return $state;
            }
        }

        if (in_array($name, self::RESERVED_STATE_NAMES, true)) {
            $state = $this->defineState($name, []);

            return $state;
        }

        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($this, $name)) {
                return $resolver->resolve($this, $name);
            }
        }

        return null;
    }

    public function getTechnicalState(): State
    {
        $state = $this->getState(State::Technical);
        assert($state !== null, 'Technical state should always exist');

        return $state;
    }

    /**
     * @param StateDetail[]|array<string,mixed> $detailsToSet
     * @param string[] $detailsToRemove
     * @throws StateModificationException
     */
    public function modifyState(StateId $stateId, array $detailsToSet = [], array $detailsToRemove = []): void
    {
        $state = $this->states[$stateId->toString()] ?? null;
        if ($state === null) {
            throw new CannotModifyStateException("Requested State to perform modify operation does not exist.");
        }

        Assert::eachString($detailsToRemove, CannotModifyStateException::class);

        $details = $state->details;
        $addedDetails = [];
        $removedDetails = [];
        foreach ($detailsToRemove as $detailToRemove) {
            $detail = $details[$detailToRemove] ?? null;
            if ($detail !== null) {
                $removedDetails[$detailToRemove] = $detail;
            }
            unset($details[$detailToRemove]);
        }

        foreach ($detailsToSet as $key => $detailToAdd) {
            if (!$detailToAdd instanceof StateDetail) {
                $detailToAdd = new StateDetail($key, $detailToAdd);
            }

            $existingDetail = $details[$detailToAdd->name] ?? null;
            if (null !== $existingDetail) {
                $removedDetails[$detailToAdd->name] = $existingDetail;
                unset($details[$detailToAdd->name]);
            }

            $addedDetails[$detailToAdd->name] = $detailToAdd;
            $details[$detailToAdd->name] = $detailToAdd;
        }

        $changedState = State::recreate(
            $state->executionId,
            $state->id,
            $state->name,
            $details,
        );

        if ($this->modificationPolicy !== null) {
            $this->modificationPolicy->assertStateModificationAllowed($state, $changedState, $this);
        }

        $this->states[$stateId->toString()] = $changedState;
        $this->recordEvent(new StateModifiedEvent($state->id, $removedDetails, $addedDetails));
    }

    /**
     * @throws StateRemovalException
     */
    public function removeState(StateId $stateId): void
    {
        $state = $this->states[$stateId->toString()] ?? null;
        if ($state === null) {
            throw new CannotRemoveStateException("Requested State to perform remove operation does not exist.");
        }

        if ($this->removalPolicy !== null) {
            $this->removalPolicy->assertStateRemovalAllowed($state, $this);
        }

        unset($this->states[$stateId->toString()]);
        $this->recordEvent(new StateRemovedEvent($stateId));
    }
}

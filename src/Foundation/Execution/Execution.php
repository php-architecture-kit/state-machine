<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Execution;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointers;
use PhpArchitecture\StateMachine\Foundation\Pointer\Policy\PointerCreationPolicy;
use PhpArchitecture\StateMachine\Foundation\Pointer\Policy\PointerRemovalPolicy;
use PhpArchitecture\StateMachine\Foundation\Pointer\Policy\PointerTransitionPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\StateDefinitionPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\StateModificationPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Policy\StateRemovalPolicy;
use PhpArchitecture\StateMachine\Foundation\State\Resolver\StateResolverInterface;
use PhpArchitecture\StateMachine\Foundation\State\States;

class Execution
{
    protected function __construct(
        public readonly ExecutionId $id,
        public readonly Pointers $pointers,
        public readonly States $states,
    ) {}

    /** @param StateResolverInterface[] $stateResolvers */
    public static function create(
        ?PointerCreationPolicy $pointerCreationPolicy = null,
        ?PointerTransitionPolicy $pointerTransitionPolicy = null,
        ?PointerRemovalPolicy $pointerRemovalPolicy = null,
        ?StateDefinitionPolicy $stateDefinitionPolicy = null,
        ?StateModificationPolicy $stateModificationPolicy = null,
        ?StateRemovalPolicy $stateRemovalPolicy = null,
        array $stateResolvers = [],
    ): static {
        $id = ExecutionId::new();

        /** @phpstan-ignore-next-line */
        return new static(
            $id,
            Pointers::create($id, $pointerCreationPolicy, $pointerTransitionPolicy, $pointerRemovalPolicy),
            States::create($id, $stateDefinitionPolicy, $stateModificationPolicy, $stateRemovalPolicy, $stateResolvers),
        );
    }

    public static function recreate(
        ExecutionId $id,
        Pointers $pointers,
        States $states,
    ): static {
        /** @phpstan-ignore-next-line */
        return new static(
            $id,
            $pointers,
            $states,
        );
    }
}

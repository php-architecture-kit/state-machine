<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State\Resolver;

use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\State\States;

interface StateResolverInterface
{
    public function supports(States $states, string $stateName): bool;
    public function resolve(States $states, string $stateName): State;
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Workflow\Transition;

use Closure;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;

class WorkflowTransition
{
    public function __construct(
        public readonly string $name,
        public readonly string $from,
        public readonly string $to,
        public readonly null|Closure|TransitionCondition $guard = null,
    ) {}
}

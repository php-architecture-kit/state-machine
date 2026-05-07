<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition\Condition;

use Closure;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;

class TransitionConditionCallback implements TransitionCondition
{
    /** @param Closure(States):TransitionConditionDecision $callback */
    public function __construct(
        private readonly Closure $callback,
    ) {}

    /** @param callable(States):TransitionConditionDecision $callback */
    public static function define(callable $callback): static
    {
        return new static(Closure::fromCallable($callback));
    }

    public function check(States $states): TransitionConditionDecision
    {
        return ($this->callback)($states);
    }
}

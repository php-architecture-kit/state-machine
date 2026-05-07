<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\SwitchCase\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\FirstValidTransitionStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionSelectionStrategy;

class SwitchCaseNode extends Node
{
    public function handlerClass(): string
    {
        return SwitchCaseNodeHandler::class;
    }

    public function transitionStrategy(): TransitionSelectionStrategy
    {
        return new FirstValidTransitionStrategy();
    }
}

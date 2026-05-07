<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\FirstValidTransitionStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionSelectionStrategy;

class AwaitAllSyncNode extends Node
{
    public function handlerClass(): string
    {
        return AwaitAllSyncNodeHandler::class;
    }

    public function transitionStrategy(): TransitionSelectionStrategy
    {
        return new FirstValidTransitionStrategy();
    }
}

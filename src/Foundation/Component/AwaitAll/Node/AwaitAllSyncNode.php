<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\FirstValidTransitionStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionSelectionStrategy;

class AwaitAllSyncNode extends Node
{
    public function __construct()
    {
        parent::__construct('php-architecture.await-all-sync.' . uniqid('', true));
    }

    public function handlerClass(): string
    {
        return AwaitAllSyncNodeHandler::class;
    }

    public function transitionStrategy(): TransitionSelectionStrategy
    {
        return new FirstValidTransitionStrategy();
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Join\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;

/**
 * Entry of one JoinComponent input; pointers wait here until every input has one.
 */
final class JoinArrivalNode extends Node
{
    public function handlerClass(): string
    {
        return JoinArrivalNodeHandler::class;
    }

    /** Pointers waiting at this input that are not part of a released round yet. */
    public function waitingCounter(): string
    {
        return $this->id->toString() . '.waiting';
    }

    /** Pointers waiting at this input whose round has already been released; they are removed. */
    public function consumedCounter(): string
    {
        return $this->id->toString() . '.consumed';
    }
}

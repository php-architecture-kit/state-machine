<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\RaceFirst\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;

/**
 * Node that determines if this pointer is the first winner.
 * Stores winner pointer ID in technical state.
 */
final class RaceConditionNode extends Node
{
    public function handlerClass(): string
    {
        return RaceConditionNodeHandler::class;
    }

    public function stateName(): string
    {
        return $this->id->toString() . '.winner';
    }
}

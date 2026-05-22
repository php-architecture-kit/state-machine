<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Loader\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionSelectionStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\AllValidTransitionsStrategy;

final class GenericNode extends Node
{
    /** @param class-string $handlerClassName */
    public function __construct(
        string $globallyUniqueName,
        private readonly string $handlerClassName,
        array $tags = [],
        TransitionSelectionStrategy $transitionStrategy = new AllValidTransitionsStrategy(),
    ) {
        parent::__construct($globallyUniqueName, $tags, $transitionStrategy);
    }

    public function handlerClass(): string
    {
        return $this->handlerClassName;
    }
}

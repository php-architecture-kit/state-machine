<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;

class AwaitAllArrivalNode extends Node
{
    /**
     * @param string $globallyUniqueName Unique name across all state machines
     * @param string[] $tags
     */
    public function __construct(
        string $globallyUniqueName,
        public readonly string $componentId,
        public readonly string $branchName,
        array $tags = [],
    ) {
        parent::__construct($globallyUniqueName, $tags);
    }

    public function handlerClass(): string
    {
        return AwaitAllArrivalNodeHandler::class;
    }
}

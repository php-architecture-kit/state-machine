<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;

class AwaitAllArrivalNode extends Node
{
    public function __construct(
        string $uniqueName,
        public readonly string $branchName,
        public readonly string $componentId,
    ) {
        parent::__construct($uniqueName);
    }

    public function handlerClass(): string
    {
        return AwaitAllArrivalNodeHandler::class;
    }
}

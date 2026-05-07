<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;

class AwaitAllArrivalNode extends Node
{
    public function __construct(
        public readonly string $componentId,
        public readonly string $branchName,
    ) {
        parent::__construct();
    }

    public function handlerClass(): string
    {
        return AwaitAllArrivalNodeHandler::class;
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Parallel\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;

class ParallelNode extends Node
{
    public function handlerClass(): string
    {
        return ParallelNodeHandler::class;
    }
}

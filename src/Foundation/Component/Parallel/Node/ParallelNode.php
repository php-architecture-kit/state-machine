<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Parallel\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;

class ParallelNode extends Node
{
    public function __construct()
    {
        parent::__construct('php-architecture.parallel.' . uniqid('', true));
    }

    public function handlerClass(): string
    {
        return ParallelNodeHandler::class;
    }
}

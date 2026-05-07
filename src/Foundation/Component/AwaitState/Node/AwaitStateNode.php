<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\AwaitState\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Node;

class AwaitStateNode extends Node
{
    public function id(): NodeId
    {
        return $this->id;
    }

    public function handlerClass(): string
    {
        return AwaitStateNodeHandler::class;
    }
}

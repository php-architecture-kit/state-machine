<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Definition;

use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use LogicException;

final class Port extends Node
{
    public protected(set) ?NodeId $attachedNode = null;

    public function handlerClass(): string
    {
        throw new LogicException('Port is a definition boundary node and cannot be handled as a state-machine node.');
    }

    public function attach(NodeId $nodeId): void
    {
        $this->attachedNode = $nodeId;
    }
}

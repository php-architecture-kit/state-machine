<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNodeHandler;

class AwaitAllSyncNode extends Node
{
    public function __construct(string $uniqueName)
    {
        parent::__construct($uniqueName);
    }

    public function handlerClass(): string
    {
        return PassthroughNodeHandler::class;
    }
}

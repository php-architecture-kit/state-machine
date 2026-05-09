<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough;

use PhpArchitecture\StateMachine\Foundation\Node\Node;

final class PassthroughNode extends Node
{
    public function handlerClass(): string
    {
        return PassthroughNodeHandler::class;
    }
}

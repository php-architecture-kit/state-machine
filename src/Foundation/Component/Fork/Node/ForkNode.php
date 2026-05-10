<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Fork\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNodeHandler;

class ForkNode extends Node
{
    public function __construct()
    {
        parent::__construct("state-machine.fork." . bin2hex(random_bytes(8)));
    }

    public function handlerClass(): string
    {
        return PassthroughNodeHandler::class;
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Choice\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\FirstValidTransitionStrategy;

class ChoiceNode extends Node
{
    public function __construct()
    {
        parent::__construct(
            "state-machine.choice." . bin2hex(random_bytes(8)),
            transitionStrategy: new FirstValidTransitionStrategy(),
        );
    }

    public function handlerClass(): string
    {
        return PassthroughNodeHandler::class;
    }
}

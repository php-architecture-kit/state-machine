<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Loader\StateMachine;

use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\StateMachine;

final class DynamicStateMachine extends StateMachine
{
    public function addNode(NodeInterface $node): static
    {
        return parent::addNode($node);
    }
}

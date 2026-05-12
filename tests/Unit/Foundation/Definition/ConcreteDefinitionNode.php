<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Definition;

use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionStrategyInterface;

class ConcreteDefinitionNode implements NodeInterface
{
    public function __construct(private string $name) {}

    public function id(): NodeId
    {
        return NodeId::create($this->name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function handlerClass(): string
    {
        return PassthroughNodeHandler::class;
    }

    public function tags(): array
    {
        return [];
    }

    public function transitionStrategy(): \PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionSelectionStrategy
    {
        return \PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionSelectionStrategy::FirstValid;
    }
}

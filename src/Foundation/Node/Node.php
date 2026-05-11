<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Exception\InvalidNodeException;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\AllValidTransitionsStrategy;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionSelectionStrategy;
use PhpArchitecture\Technical\Assert;

abstract class Node implements NodeInterface
{
    public readonly NodeId $id;

    /**
     * @param string $globallyUniqueName Unique name across all state machines - cannot be ever changed. Required format: {vendor}.{module}.{purpose}
     * @param string[] $tags
     */
    public function __construct(
        public readonly string $globallyUniqueName,
        public readonly array $tags = [],
        public readonly TransitionSelectionStrategy $transitionStrategy = new AllValidTransitionsStrategy(),
    ) {
        if (!preg_match('/^[a-zA-Z0-9\-_]+(\.[a-zA-Z0-9\-_]+)*$/', $globallyUniqueName)) {
            throw new InvalidNodeException('Globally unique name must be in format {vendor}.{module}.{purpose}');
        }
        $this->id = NodeId::create($globallyUniqueName);

        Assert::eachString($tags, InvalidNodeException::class);
    }

    public function id(): NodeId
    {
        return $this->id;
    }

    /** @return class-string */
    abstract public function handlerClass(): string;

    public function name(): string
    {
        return $this->globallyUniqueName;
    }

    /** @return string[] */
    public function tags(): array
    {
        return $this->tags;
    }

    public function transitionStrategy(): TransitionSelectionStrategy
    {
        return $this->transitionStrategy;
    }
}

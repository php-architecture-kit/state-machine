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
    private readonly array $tags;

    /**
     * @param string[] $tags
     */
    public function __construct(
        ?NodeId $id = null,
        array $tags = [],
    ) {
        Assert::eachString($tags, InvalidNodeException::class);
        $this->id = $id ?? NodeId::new();
        $this->tags = $tags;
    }

    public function id(): NodeId
    {
        return $this->id;
    }

    /** @return class-string */
    abstract public function handlerClass(): string;

    /** @return string[] */
    public function tags(): array
    {
        return $this->tags;
    }

    public function transitionStrategy(): TransitionSelectionStrategy
    {
        return new AllValidTransitionsStrategy();
    }
}

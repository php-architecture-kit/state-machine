<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition;

use PhpArchitecture\Graph\Edge\EdgeType;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\Exception\InvalidTransitionException;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\Identity\TransitionId;
use PhpArchitecture\Technical\Assert;

class Transition implements TransitionInterface
{
    /**
     * @param string[] $tags
     */
    protected function __construct(
        public readonly TransitionId $id,
        public readonly NodeId $input,
        public readonly NodeId $output,
        public readonly ?TransitionCondition $condition,
        public readonly array $tags,
    ) {
        Assert::eachString($this->tags, InvalidTransitionException::class);
    }

    /**
     * @param string[] $tags
     */
    public static function create(
        NodeId $input,
        NodeId $output,
        ?TransitionCondition $condition = null,
        array $tags = [],
    ): static {
        /** @phpstan-ignore-next-line */
        return new static(
            TransitionId::new(),
            $input,
            $output,
            $condition,
            $tags,
        );
    }

    /**
     * @param string[] $tags
     */
    public static function recreate(
        TransitionId $id,
        NodeId $input,
        NodeId $output,
        ?TransitionCondition $condition,
        array $tags,
    ): static {
        /** @phpstan-ignore-next-line */
        return new static(
            $id,
            $input,
            $output,
            $condition,
            $tags,
        );
    }

    public function id(): TransitionId
    {
        return $this->id;
    }

    public function u(): NodeId
    {
        return $this->input;
    }

    public function v(): NodeId
    {
        return $this->output;
    }

    /** @return string[] */
    public function tags(): array
    {
        return $this->tags;
    }

    public function type(): EdgeType
    {
        return EdgeType::Directed;
    }

    public function condition(): ?TransitionCondition
    {
        return $this->condition;
    }

    public function withInput(NodeId $nodeId): self
    {
        return new self(
            $this->id,
            $nodeId,
            $this->output,
            $this->condition,
            $this->tags,
        );
    }

    public function withOutput(NodeId $nodeId): self
    {
        return new self(
            $this->id,
            $this->input,
            $nodeId,
            $this->condition,
            $this->tags,
        );
    }
}

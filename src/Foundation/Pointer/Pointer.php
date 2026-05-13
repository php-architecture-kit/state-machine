<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer;

use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Identity\PointerId;
use PhpArchitecture\Technical\Assert;

class Pointer
{
    /**
     * @param PointerId[] $parentIds
     */
    protected function __construct(
        public readonly ExecutionId $executionId,
        public readonly PointerId $id,
        public readonly array $parentIds,
        public protected(set) NodeId $nodeId,
        public protected(set) int $currentStep,
        public protected(set) NodeHandlingStatus $handlingStatus,
    ) {
        Assert::eachInstanceOf($parentIds, PointerId::class);
    }

    /**
     * @param PointerId[] $parentIds
     */
    public static function create(
        ExecutionId $executionId,
        NodeId $nodeId,
        array $parentIds = [],
    ): self {
        return new self(
            $executionId,
            PointerId::new(),
            $parentIds,
            $nodeId,
            0,
            NodeHandlingStatus::Pending,
        );
    }

    public function fork(): Pointer
    {
        return new Pointer(
            $this->executionId,
            PointerId::new(),
            [$this->id],
            $this->nodeId,
            $this->currentStep,
            NodeHandlingStatus::Pending,
        );
    }

    public function step(NodeId $nodeId): void
    {
        $this->nodeId = $nodeId;
        $this->currentStep++;
        $this->handlingStatus = NodeHandlingStatus::Pending;
    }

    public function markNodeHandlingStatusCompleted(): void
    {
        $this->handlingStatus = NodeHandlingStatus::Completed;
    }
}

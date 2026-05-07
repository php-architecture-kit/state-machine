<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Identity;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Identity\PointerId;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Identity\InvalidTaskKeyFormatException;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskStamp;
use Stringable;

final readonly class TaskKey implements TaskStamp, Stringable
{
    public function __construct(
        public NodeId $nodeId,
        public ExecutionId $executionId,
        public PointerId $pointerId,
        public string $taskName,
    ) {}

    public static function fromString(string $key): self
    {
        $parts = explode(':', $key);

        if (count($parts) !== 5 || $parts[0] !== 'task') {
            throw new InvalidTaskKeyFormatException("Invalid task key format: '{$key}'. Expected 'task:<nodeId>:<executionId>:<pointerId>:<taskName>'.");
        }

        return new self(
            NodeId::fromString($parts[1]),
            ExecutionId::fromString($parts[2]),
            PointerId::fromString($parts[3]),
            $parts[4],
        );
    }

    public function __toString(): string
    {
        return sprintf(
            'task:%s:%s:%s:%s',
            $this->nodeId->toString(),
            $this->executionId->toString(),
            $this->pointerId->toString(),
            $this->taskName,
        );
    }
}

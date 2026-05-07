<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Node\Handler;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Exception\InvalidNodeException;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Task\Identity\TaskKey;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Dispatch\InvalidTaskStampException;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskBusInterface;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskStamp;
use PhpArchitecture\Technical\Assert;

readonly class NodeHandlerContext
{
    public function __construct(
        public ExecutionId $executionId,
        public NodeInterface $node,
        public Pointer $pointer,
        public States $states,
        private TaskBusInterface $taskBus,
    ) {
        if (!$pointer->nodeId->equals($node->id())) {
            throw new InvalidNodeException(
                "Pointer node ID '{$pointer->nodeId}' does not match handler context node ID '{$node->id()}'.",
            );
        }
    }

    public function dispatchTask(Task $task, array $stamps = []): void
    {
        Assert::eachInstanceOf($stamps, TaskStamp::class, InvalidTaskStampException::class);

        if (empty(array_filter($stamps, static fn(TaskStamp $stamp) => $stamp instanceof TaskKey))) {
            $stamps[] = new TaskKey(
                $this->node->id(),
                $this->executionId,
                $this->pointer->id,
                $task::class,
            );
        }

        $this->taskBus->dispatch($task, $stamps);
    }
}

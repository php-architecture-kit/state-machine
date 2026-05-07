<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Node\Handler;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Exception\InvalidNodeException;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointers;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Dispatch\InvalidTaskStampException;
use PhpArchitecture\StateMachine\Foundation\Task\Identity\TaskKey;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskBusInterface;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskStamp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class NodeHandlerContextTest extends TestCase
{
    private function makeNode(NodeId $id): Node
    {
        return new class($id) extends Node {
            public function __construct(NodeId $id)
            {
                parent::__construct($id);
            }

            public function id(): NodeId
            {
                return $this->id;
            }

            public function handlerClass(): string
            {
                return stdClass::class;
            }
        };
    }

    #[Test]
    public function constructorStoresAllFieldsCorrectly(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::new();
        $node = $this->makeNode($nodeId);
        $pointer = Pointer::create($executionId, $nodeId);
        $states = States::create($executionId, null, null, null);
        $bus = $this->createMock(TaskBusInterface::class);

        $context = new NodeHandlerContext($executionId, $node, $pointer, $states, $bus);

        $this->assertSame($executionId, $context->executionId);
        $this->assertSame($node, $context->node);
        $this->assertSame($pointer, $context->pointer);
        $this->assertSame($states, $context->states);
    }

    #[Test]
    public function constructorThrowsInvalidNodeExceptionWhenPointerNodeIdDiffersFromContextNodeId(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::new();
        $differentNodeId = NodeId::new();
        $node = $this->makeNode($differentNodeId);
        $pointer = Pointer::create($executionId, $nodeId);
        $states = States::create($executionId, null, null, null);
        $bus = $this->createMock(TaskBusInterface::class);

        $this->expectException(InvalidNodeException::class);

        new NodeHandlerContext($executionId, $node, $pointer, $states, $bus);
    }

    #[Test]
    public function dispatchTaskCallsBusWithTaskAndAutoInjectedTaskKeyStamp(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::new();
        $node = $this->makeNode($nodeId);
        $pointer = Pointer::create($executionId, $nodeId);
        $states = States::create($executionId, null, null, null);
        $task = new class implements Task {};

        $dispatchedStamps = [];
        $bus = $this->createMock(TaskBusInterface::class);
        $bus->method('dispatch')
            ->willReturnCallback(function (Task $t, array $stamps) use (&$dispatchedStamps, $task): TaskEnvelope {
                $dispatchedStamps = $stamps;
                return TaskEnvelope::create($task);
            });

        $context = new NodeHandlerContext($executionId, $node, $pointer, $states, $bus);
        $context->dispatchTask($task);

        $keys = array_filter($dispatchedStamps, fn($s) => $s instanceof TaskKey);
        $this->assertCount(1, $keys);

        /** @var TaskKey $key */
        $key = reset($keys);
        $this->assertTrue($nodeId->equals($key->nodeId));
        $this->assertTrue($executionId->equals($key->executionId));
        $this->assertTrue($pointer->id->equals($key->pointerId));
    }

    #[Test]
    public function dispatchTaskDoesNotInjectTaskKeyStampWhenOneIsAlreadyProvided(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::new();
        $node = $this->makeNode($nodeId);
        $pointer = Pointer::create($executionId, $nodeId);
        $states = States::create($executionId, null, null, null);
        $task = new class implements Task {};
        $providedKey = new TaskKey($nodeId, $executionId, $pointer->id, $task::class);

        $dispatchedStamps = [];
        $bus = $this->createMock(TaskBusInterface::class);
        $bus->method('dispatch')
            ->willReturnCallback(function (Task $t, array $stamps) use (&$dispatchedStamps, $task, $providedKey): TaskEnvelope {
                $dispatchedStamps = $stamps;
                return TaskEnvelope::create($task, [$providedKey]);
            });

        $context = new NodeHandlerContext($executionId, $node, $pointer, $states, $bus);
        $context->dispatchTask($task, [$providedKey]);

        $keys = array_filter($dispatchedStamps, fn($s) => $s instanceof TaskKey);
        $this->assertCount(1, $keys);
        $this->assertSame($providedKey, reset($keys));
    }

    #[Test]
    public function dispatchTaskThrowsInvalidTaskStampExceptionOnNonStamp(): void
    {
        $executionId = ExecutionId::new();
        $nodeId = NodeId::new();
        $node = $this->makeNode($nodeId);
        $pointer = Pointer::create($executionId, $nodeId);
        $states = States::create($executionId, null, null, null);
        $bus = $this->createMock(TaskBusInterface::class);
        $task = new class implements Task {};

        $this->expectException(InvalidTaskStampException::class);

        $context = new NodeHandlerContext($executionId, $node, $pointer, $states, $bus);
        $context->dispatchTask($task, [new stdClass()]);
    }
}

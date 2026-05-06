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

        $context = new NodeHandlerContext($executionId, $node, $pointer, $states);

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

        $this->expectException(InvalidNodeException::class);

        new NodeHandlerContext($executionId, $node, $pointer, $states);
    }
}

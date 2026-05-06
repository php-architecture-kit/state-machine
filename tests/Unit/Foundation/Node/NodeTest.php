<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Exception\InvalidNodeException;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\AllValidTransitionsStrategy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class NodeTest extends TestCase
{
    private function makeNode(NodeId $id, array $tags = []): Node
    {
        return new class($id, $tags) extends Node {
            public function __construct(NodeId $id, array $tags = [])
            {
                parent::__construct($id, $tags);
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
    public function tagsReturnsEmptyArrayByDefault(): void
    {
        $node = $this->makeNode(NodeId::new());

        $this->assertSame([], $node->tags());
    }

    #[Test]
    public function tagsReturnsProvidedStringArray(): void
    {
        $node = $this->makeNode(NodeId::new(), ['tagA', 'tagB']);

        $this->assertSame(['tagA', 'tagB'], $node->tags());
    }

    #[Test]
    public function constructorThrowsInvalidNodeExceptionOnNonStringTag(): void
    {
        $this->expectException(InvalidNodeException::class);

        $this->makeNode(NodeId::new(), [42]);
    }

    #[Test]
    public function transitionStrategyReturnsAllValidTransitionsStrategyByDefault(): void
    {
        $node = $this->makeNode(NodeId::new());

        $this->assertInstanceOf(AllValidTransitionsStrategy::class, $node->transitionStrategy());
    }

    #[Test]
    public function handlerClassReturnsDefinedHandlerClass(): void
    {
        $node = $this->makeNode(NodeId::new());

        $this->assertSame(stdClass::class, $node->handlerClass());
    }

    #[Test]
    public function idReturnsConstructedNodeId(): void
    {
        $id = NodeId::new();
        $node = $this->makeNode($id);

        $this->assertTrue($id->equals($node->id()));
    }
}

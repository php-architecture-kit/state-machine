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
    private function makeNode(string $name, array $tags = []): Node
    {
        return new class($name, $tags) extends Node {
            public function handlerClass(): string
            {
                return stdClass::class;
            }
        };
    }

    #[Test]
    public function globallyUniqueNameIsStoredCorrectly(): void
    {
        $node = $this->makeNode('state-machine.node.test');

        $this->assertSame('state-machine.node.test', $node->globallyUniqueName);
    }

    #[Test]
    public function tagsReturnsEmptyArrayByDefault(): void
    {
        $node = $this->makeNode('state-machine.node.test');

        $this->assertSame([], $node->tags());
    }

    #[Test]
    public function tagsReturnsProvidedStringArray(): void
    {
        $node = $this->makeNode('state-machine.node.test', ['tagA', 'tagB']);

        $this->assertSame(['tagA', 'tagB'], $node->tags());
    }

    #[Test]
    public function constructorThrowsInvalidNodeExceptionOnNonStringTag(): void
    {
        $this->expectException(InvalidNodeException::class);

        $this->makeNode('state-machine.node.test', [42]);
    }

    #[Test]
    public function transitionStrategyReturnsAllValidTransitionsStrategyByDefault(): void
    {
        $node = $this->makeNode('state-machine.node.test');

        $this->assertInstanceOf(AllValidTransitionsStrategy::class, $node->transitionStrategy());
    }

    #[Test]
    public function handlerClassReturnsDefinedHandlerClass(): void
    {
        $node = $this->makeNode('state-machine.node.test');

        $this->assertSame(stdClass::class, $node->handlerClass());
    }

    #[Test]
    public function idReturnsNodeIdDerivedFromGloballyUniqueName(): void
    {
        $node = $this->makeNode('state-machine.node.test');

        $this->assertInstanceOf(NodeId::class, $node->id());
        $this->assertSame(5, $node->id()->getVersion());
    }
}

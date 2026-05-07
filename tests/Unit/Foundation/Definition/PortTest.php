<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Definition;

use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PortTest extends TestCase
{
    #[Test]
    public function nameIsStoredCorrectly(): void
    {
        $port = new Port('input_port');

        $this->assertSame('input_port', $port->name);
    }

    #[Test]
    public function attachedNodeIsNullByDefault(): void
    {
        $port = new Port('my_port');

        $this->assertNull($port->attachedNode);
    }

    #[Test]
    public function idReturnsNodeIdInstance(): void
    {
        $port = new Port('my_port');

        $this->assertInstanceOf(NodeId::class, $port->id());
    }

    #[Test]
    public function tagsContainPortTag(): void
    {
        $port = new Port('my_port');

        $this->assertContains('port', $port->tags());
    }

    #[Test]
    public function attachSetsAttachedNode(): void
    {
        $port = new Port('my_port');
        $nodeId = NodeId::new();

        $port->attach($nodeId);

        $this->assertTrue($nodeId->equals($port->attachedNode));
    }

    #[Test]
    public function attachOverwritesPreviouslyAttachedNode(): void
    {
        $port = new Port('my_port');
        $firstNodeId = NodeId::new();
        $secondNodeId = NodeId::new();

        $port->attach($firstNodeId);
        $port->attach($secondNodeId);

        $this->assertTrue($secondNodeId->equals($port->attachedNode));
    }
}

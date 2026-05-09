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
    public function globallyUniqueNameIsStoredCorrectly(): void
    {
        $port = new Port('state-machine.port.input');

        $this->assertSame('state-machine.port.input', $port->globallyUniqueName);
    }

    #[Test]
    public function attachedNodeIsNullByDefault(): void
    {
        $port = new Port('state-machine.port.test');

        $this->assertNull($port->attachedNode);
    }

    #[Test]
    public function idReturnsNodeIdInstance(): void
    {
        $port = new Port('state-machine.port.test');

        $this->assertInstanceOf(NodeId::class, $port->id());
    }

    #[Test]
    public function attachSetsAttachedNode(): void
    {
        $port = new Port('state-machine.port.test');
        $nodeId = NodeId::create("state-machine.unit.foundation.definition.porttest.node1");

        $port->attach($nodeId);

        $this->assertTrue($nodeId->equals($port->attachedNode));
    }

    #[Test]
    public function attachOverwritesPreviouslyAttachedNode(): void
    {
        $port = new Port('state-machine.port.test');
        $firstNodeId = NodeId::create("state-machine.unit.foundation.definition.porttest.node2");
        $secondNodeId = NodeId::create("state-machine.unit.foundation.definition.porttest.node3");

        $port->attach($firstNodeId);
        $port->attach($secondNodeId);

        $this->assertTrue($secondNodeId->equals($port->attachedNode));
    }
}

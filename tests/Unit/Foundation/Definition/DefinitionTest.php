<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Definition;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class DefinitionTest extends TestCase
{
    private function makeDefinition(): ConcreteDefinition
    {
        return new ConcreteDefinition();
    }

    private function makeNode(string $name): ConcreteDefinitionNode
    {
        return new ConcreteDefinitionNode($name);
    }

    #[Test]
    public function regularNodesAreIncludedInDefinedNodes(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $nodeB = $this->makeNode('state-machine.definition.node-b');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($nodeB);

        [$nodes] = $definition->getDefinedNodesAndTransitions();

        $this->assertCount(2, $nodes);
    }

    #[Test]
    public function definedNodesContainNoPortInstances(): void
    {
        $definition = $this->makeDefinition();
        $regularNode = $this->makeNode('state-machine.definition.regular');
        $definition->addNodePublic($regularNode);

        $port = new Port('state-machine.definition.port.input');
        $port->attach($regularNode->id());
        $definition->addNodePublic($port);

        [$nodes] = $definition->getDefinedNodesAndTransitions();

        foreach ($nodes as $node) {
            $this->assertNotInstanceOf(Port::class, $node);
        }
    }

    #[Test]
    public function portWithoutAttachedNodeRemovesItsTransitions(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $nodeB = $this->makeNode('state-machine.definition.node-b');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($nodeB);

        $port = new Port('state-machine.definition.port.unattached');
        $definition->addNodePublic($port);

        $definition->addTransitionPublic($nodeA->id(), $port->id());
        $definition->addTransitionPublic($port->id(), $nodeB->id());

        [, $transitions] = $definition->getDefinedNodesAndTransitions();

        $this->assertEmpty($transitions);
    }

    #[Test]
    public function portWithoutAttachedNodeDoesNotRemoveUnrelatedTransitions(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $nodeB = $this->makeNode('state-machine.definition.node-b');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($nodeB);

        $port = new Port('state-machine.definition.port.unattached');
        $definition->addNodePublic($port);

        $definition->addTransitionPublic($nodeA->id(), $nodeB->id());
        $definition->addTransitionPublic($nodeA->id(), $port->id());

        [, $transitions] = $definition->getDefinedNodesAndTransitions();

        $this->assertCount(1, $transitions);
        $this->assertTrue($nodeA->id()->equals(array_values($transitions)[0]->u()));
        $this->assertTrue($nodeB->id()->equals(array_values($transitions)[0]->v()));
    }

    #[Test]
    public function portAsSourceIsReplacedWithAttachedNodeIdInTransition(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $externalNode = $this->makeNode('state-machine.definition.external');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($externalNode);

        $port = new Port('state-machine.definition.port.output');
        $port->attach($externalNode->id());
        $definition->addNodePublic($port);

        $definition->addTransitionPublic($port->id(), $nodeA->id());

        [, $transitions] = $definition->getDefinedNodesAndTransitions();

        $transition = array_values($transitions)[0];
        $this->assertTrue($externalNode->id()->equals($transition->u()));
        $this->assertTrue($nodeA->id()->equals($transition->v()));
    }

    #[Test]
    public function portAsTargetIsReplacedWithAttachedNodeIdInTransition(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $externalNode = $this->makeNode('state-machine.definition.external');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($externalNode);

        $port = new Port('state-machine.definition.port.input');
        $port->attach($externalNode->id());
        $definition->addNodePublic($port);

        $definition->addTransitionPublic($nodeA->id(), $port->id());

        [, $transitions] = $definition->getDefinedNodesAndTransitions();

        $transition = array_values($transitions)[0];
        $this->assertTrue($nodeA->id()->equals($transition->u()));
        $this->assertTrue($externalNode->id()->equals($transition->v()));
    }

    #[Test]
    public function attachedNodeIdMayBeExternalToDefinitionWithoutError(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $externalNode = $this->makeNode('state-machine.definition.external');
        $definition->addNodePublic($nodeA);

        $port = new Port('state-machine.definition.port.boundary');
        $port->attach($externalNode->id());
        $definition->addNodePublic($port);

        $definition->addTransitionPublic($nodeA->id(), $port->id());

        [$nodes, $transitions] = $definition->getDefinedNodesAndTransitions();

        $this->assertCount(1, $nodes);
        $this->assertCount(1, $transitions);
    }

    #[Test]
    public function addTransitionWithNodeInterfaceFromAutoRegistersNodeAndUsesItsId(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $nodeB = $this->makeNode('state-machine.definition.node-b');
        $definition->addNodePublic($nodeB);

        $definition->addTransitionWithNodePublic($nodeA, $nodeB->id());

        [$nodes, $transitions] = $definition->getDefinedNodesAndTransitions();

        $this->assertCount(2, $nodes);
        $this->assertCount(1, $transitions);
        $this->assertTrue($nodeA->id()->equals(array_values($transitions)[0]->u()));
    }

    #[Test]
    public function addTransitionWithNodeInterfaceToAutoRegistersNodeAndUsesItsId(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $nodeB = $this->makeNode('state-machine.definition.node-b');
        $definition->addNodePublic($nodeA);

        $definition->addTransitionWithNodePublic($nodeA->id(), $nodeB);

        [$nodes, $transitions] = $definition->getDefinedNodesAndTransitions();

        $this->assertCount(2, $nodes);
        $this->assertCount(1, $transitions);
        $this->assertTrue($nodeB->id()->equals(array_values($transitions)[0]->v()));
    }

    #[Test]
    public function addTransitionWithAlreadyRegisteredNodeDoesNotDuplicateIt(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $nodeB = $this->makeNode('state-machine.definition.node-b');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($nodeB);

        $definition->addTransitionWithNodePublic($nodeA, $nodeB);

        [$nodes] = $definition->getDefinedNodesAndTransitions();
        $this->assertCount(2, $nodes);
    }

    #[Test]
    public function mixedScenarioWithAttachedAndUnattachedPortsProducesCorrectResult(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $nodeB = $this->makeNode('state-machine.definition.node-b');
        $externalNode = $this->makeNode('state-machine.definition.external');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($nodeB);

        $attachedPort = new Port('state-machine.definition.port.attached');
        $attachedPort->attach($externalNode->id());
        $definition->addNodePublic($attachedPort);

        $unattachedPort = new Port('state-machine.definition.port.unattached');
        $definition->addNodePublic($unattachedPort);

        $definition->addTransitionPublic($nodeA->id(), $nodeB->id());
        $definition->addTransitionPublic($nodeA->id(), $attachedPort->id());
        $definition->addTransitionPublic($unattachedPort->id(), $nodeB->id());

        [$nodes, $transitions] = $definition->getDefinedNodesAndTransitions();

        $this->assertCount(2, $nodes);
        $this->assertCount(2, $transitions);
    }
}

class ConcreteDefinition extends Definition
{
    public function __construct()
    {
        parent::__construct(new stdClass(), new stdClass());
    }

    public function addNodePublic(NodeInterface $node): static
    {
        return $this->addNode($node);
    }

    public function addTransitionPublic(NodeId $from, NodeId $to): static
    {
        return $this->addTransition($from, $to);
    }

    public function addTransitionWithNodePublic(NodeId|NodeInterface $from, NodeId|NodeInterface $to): static
    {
        return $this->addTransition($from, $to);
    }
}

class ConcreteDefinitionNode extends Node
{
    public function handlerClass(): string
    {
        return stdClass::class;
    }
}

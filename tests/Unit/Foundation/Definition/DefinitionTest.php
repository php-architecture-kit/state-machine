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

    private function makeNode(NodeId $id): ConcreteDefinitionNode
    {
        return new ConcreteDefinitionNode($id);
    }

    #[Test]
    public function regularNodesAreIncludedInDefinedNodes(): void
    {
        $definition = $this->makeDefinition();
        $nodeIdA = NodeId::new();
        $nodeIdB = NodeId::new();
        $definition->addNodePublic($this->makeNode($nodeIdA));
        $definition->addNodePublic($this->makeNode($nodeIdB));

        [$nodes] = $definition->getDefinedNodesAndTransitions();

        $this->assertCount(2, $nodes);
    }

    #[Test]
    public function definedNodesContainNoPortInstances(): void
    {
        $definition = $this->makeDefinition();
        $regularNodeId = NodeId::new();
        $definition->addNodePublic($this->makeNode($regularNodeId));

        $port = new Port('input');
        $externalNodeId = NodeId::new();
        $port->attach($externalNodeId);
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

        $nodeIdA = NodeId::new();
        $nodeIdB = NodeId::new();
        $definition->addNodePublic($this->makeNode($nodeIdA));
        $definition->addNodePublic($this->makeNode($nodeIdB));

        $port = new Port('unattached_input');
        $definition->addNodePublic($port);

        $definition->addTransitionPublic($nodeIdA, $port->id());
        $definition->addTransitionPublic($port->id(), $nodeIdB);

        [, $transitions] = $definition->getDefinedNodesAndTransitions();

        $this->assertEmpty($transitions, 'All transitions referencing an unattached port must be removed.');
    }

    #[Test]
    public function portWithoutAttachedNodeDoesNotRemoveUnrelatedTransitions(): void
    {
        $definition = $this->makeDefinition();

        $nodeIdA = NodeId::new();
        $nodeIdB = NodeId::new();
        $definition->addNodePublic($this->makeNode($nodeIdA));
        $definition->addNodePublic($this->makeNode($nodeIdB));

        $port = new Port('unattached');
        $definition->addNodePublic($port);

        $definition->addTransitionPublic($nodeIdA, $nodeIdB);
        $definition->addTransitionPublic($nodeIdA, $port->id());

        [, $transitions] = $definition->getDefinedNodesAndTransitions();

        $this->assertCount(1, $transitions);
        $this->assertTrue($nodeIdA->equals(array_values($transitions)[0]->u()));
        $this->assertTrue($nodeIdB->equals(array_values($transitions)[0]->v()));
    }

    #[Test]
    public function portAsSourceIsReplacedWithAttachedNodeIdInTransition(): void
    {
        $definition = $this->makeDefinition();

        $nodeIdA = NodeId::new();
        $nodeIdB = NodeId::new();
        $definition->addNodePublic($this->makeNode($nodeIdA));
        $definition->addNodePublic($this->makeNode($nodeIdB));

        $port = new Port('output_port');
        $externalNodeId = NodeId::new();
        $port->attach($externalNodeId);
        $definition->addNodePublic($port);

        $definition->addTransitionPublic($port->id(), $nodeIdA);

        [, $transitions] = $definition->getDefinedNodesAndTransitions();

        $transition = array_values($transitions)[0];
        $this->assertTrue(
            $externalNodeId->equals($transition->u()),
            'Source of transition should be replaced with the attached node id.',
        );
        $this->assertTrue($nodeIdA->equals($transition->v()));
    }

    #[Test]
    public function portAsTargetIsReplacedWithAttachedNodeIdInTransition(): void
    {
        $definition = $this->makeDefinition();

        $nodeIdA = NodeId::new();
        $nodeIdB = NodeId::new();
        $definition->addNodePublic($this->makeNode($nodeIdA));
        $definition->addNodePublic($this->makeNode($nodeIdB));

        $port = new Port('input_port');
        $externalNodeId = NodeId::new();
        $port->attach($externalNodeId);
        $definition->addNodePublic($port);

        $definition->addTransitionPublic($nodeIdA, $port->id());

        [, $transitions] = $definition->getDefinedNodesAndTransitions();

        $transition = array_values($transitions)[0];
        $this->assertTrue($nodeIdA->equals($transition->u()));
        $this->assertTrue(
            $externalNodeId->equals($transition->v()),
            'Target of transition should be replaced with the attached node id.',
        );
    }

    #[Test]
    public function attachedNodeIdMayBeExternalToDefinitionWithoutError(): void
    {
        $definition = $this->makeDefinition();

        $nodeIdA = NodeId::new();
        $definition->addNodePublic($this->makeNode($nodeIdA));

        $port = new Port('boundary_port');
        $externalNodeId = NodeId::new();
        $port->attach($externalNodeId);
        $definition->addNodePublic($port);

        $definition->addTransitionPublic($nodeIdA, $port->id());

        [$nodes, $transitions] = $definition->getDefinedNodesAndTransitions();

        $this->assertCount(1, $nodes);
        $this->assertCount(1, $transitions);
        $this->assertTrue($externalNodeId->equals(array_values($transitions)[0]->v()));
    }

    #[Test]
    public function mixedScenarioWithAttachedAndUnattachedPortsProducesCorrectResult(): void
    {
        $definition = $this->makeDefinition();

        $nodeIdA = NodeId::new();
        $nodeIdB = NodeId::new();
        $definition->addNodePublic($this->makeNode($nodeIdA));
        $definition->addNodePublic($this->makeNode($nodeIdB));

        $attachedPort = new Port('attached');
        $externalNodeId = NodeId::new();
        $attachedPort->attach($externalNodeId);
        $definition->addNodePublic($attachedPort);

        $unattachedPort = new Port('unattached');
        $definition->addNodePublic($unattachedPort);

        $definition->addTransitionPublic($nodeIdA, $nodeIdB);
        $definition->addTransitionPublic($nodeIdA, $attachedPort->id());
        $definition->addTransitionPublic($unattachedPort->id(), $nodeIdB);

        [$nodes, $transitions] = $definition->getDefinedNodesAndTransitions();

        foreach ($nodes as $node) {
            $this->assertNotInstanceOf(Port::class, $node);
        }
        $this->assertCount(2, $nodes);

        $transitionValues = array_values($transitions);
        $this->assertCount(2, $transitionValues);

        $targets = array_map(static fn(TransitionInterface $t): string => $t->v()->toString(), $transitionValues);
        $this->assertNotContains($unattachedPort->id()->toString(), $targets);

        $sources = array_map(static fn(TransitionInterface $t): string => $t->u()->toString(), $transitionValues);
        $this->assertNotContains($attachedPort->id()->toString(), $sources);

        $this->assertContains($externalNodeId->toString(), $targets);
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
}

class ConcreteDefinitionNode extends Node
{
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
}

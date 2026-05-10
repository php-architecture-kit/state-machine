<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Definition;

use PhpArchitecture\Graph\Graph;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Definition\SubGraphDefinition;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use LogicException;

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

    #[Test]
    public function mergeThrowsOnDuplicateFreeInputPortNames(): void
    {
        $a1 = new ConcreteDefinitionNode('test.merge.guard.a1');
        $b1 = new ConcreteDefinitionNode('test.merge.guard.b1');

        $graph1 = new Graph();
        $graph1->vertexStore->addVertex($a1);
        $graph1->vertexStore->addVertex($b1);
        $graph1->edgeStore->addEdge(Transition::create($a1->id, $b1->id));

        $def1 = SubGraphDefinition::create(
            name: 'test.merge.guard.def1',
            graph: $graph1,
            inputs: ['shared' => ['node' => $a1]],
            outputs: ['out1' => ['node' => $b1]],
        );

        $c1 = new ConcreteDefinitionNode('test.merge.guard.c1');
        $d1 = new ConcreteDefinitionNode('test.merge.guard.d1');

        $graph2 = new Graph();
        $graph2->vertexStore->addVertex($c1);
        $graph2->vertexStore->addVertex($d1);
        $graph2->edgeStore->addEdge(Transition::create($c1->id, $d1->id));

        $def2 = SubGraphDefinition::create(
            name: 'test.merge.guard.def2',
            graph: $graph2,
            inputs: ['shared' => ['node' => $c1]],
            outputs: ['out2' => ['node' => $d1]],
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('duplicate input port names found: shared');

        SubGraphDefinition::merge(
            name: 'test.merge.guard.merged',
            first: $def1,
            second: $def2,
            inputPortMapping: [],
            outputPortMapping: [],
        );
    }

    #[Test]
    public function mergeThrowsOnDuplicateFreeOutputPortNames(): void
    {
        $a1 = new ConcreteDefinitionNode('test.merge.guard2.a1');
        $b1 = new ConcreteDefinitionNode('test.merge.guard2.b1');

        $graph1 = new Graph();
        $graph1->vertexStore->addVertex($a1);
        $graph1->vertexStore->addVertex($b1);
        $graph1->edgeStore->addEdge(Transition::create($a1->id, $b1->id));

        $def1 = SubGraphDefinition::create(
            name: 'test.merge.guard2.def1',
            graph: $graph1,
            inputs: ['in1' => ['node' => $a1]],
            outputs: ['shared' => ['node' => $b1]],
        );

        $c1 = new ConcreteDefinitionNode('test.merge.guard2.c1');
        $d1 = new ConcreteDefinitionNode('test.merge.guard2.d1');

        $graph2 = new Graph();
        $graph2->vertexStore->addVertex($c1);
        $graph2->vertexStore->addVertex($d1);
        $graph2->edgeStore->addEdge(Transition::create($c1->id, $d1->id));

        $def2 = SubGraphDefinition::create(
            name: 'test.merge.guard2.def2',
            graph: $graph2,
            inputs: ['in2' => ['node' => $c1]],
            outputs: ['shared' => ['node' => $d1]],
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('duplicate output port names found: shared');

        SubGraphDefinition::merge(
            name: 'test.merge.guard2.merged',
            first: $def1,
            second: $def2,
            inputPortMapping: [],
            outputPortMapping: [],
        );
    }

    #[Test]
    public function mergeConnectsTwoDefinitionsAndExposesCorrectPortsAndTransitions(): void
    {
        // Definition 1:
        // inputs: in1, in2, in3  ->  layer1: a1, a2, a3  ->  layer2: b1, b2  ->  outputs: out1, out2
        $a1 = new ConcreteDefinitionNode('test.merge.a1');
        $a2 = new ConcreteDefinitionNode('test.merge.a2');
        $a3 = new ConcreteDefinitionNode('test.merge.a3');
        $b1 = new ConcreteDefinitionNode('test.merge.b1');
        $b2 = new ConcreteDefinitionNode('test.merge.b2');

        $graph1 = new Graph();
        $graph1->vertexStore->addVertex($a1);
        $graph1->vertexStore->addVertex($a2);
        $graph1->vertexStore->addVertex($a3);
        $graph1->vertexStore->addVertex($b1);
        $graph1->vertexStore->addVertex($b2);
        $graph1->edgeStore->addEdge(Transition::create($a1->id, $b1->id));
        $graph1->edgeStore->addEdge(Transition::create($a2->id, $b1->id));
        $graph1->edgeStore->addEdge(Transition::create($a2->id, $b2->id));
        $graph1->edgeStore->addEdge(Transition::create($a3->id, $b2->id));

        $def1 = SubGraphDefinition::create(
            name: 'test.merge.def1',
            graph: $graph1,
            inputs: [
                'in1' => ['node' => $a1],
                'in2' => ['node' => $a2],
                'in3' => ['node' => $a3],
            ],
            outputs: [
                'out1' => ['node' => $b1],
                'out2' => ['node' => $b2],
            ],
        );

        // Definition 2:
        // inputs: in4, in5, in6  ->  layer1: c1, c2, c3  ->  layer2: d1, d2  ->  outputs: out3, out4
        $c1 = new ConcreteDefinitionNode('test.merge.c1');
        $c2 = new ConcreteDefinitionNode('test.merge.c2');
        $c3 = new ConcreteDefinitionNode('test.merge.c3');
        $d1 = new ConcreteDefinitionNode('test.merge.d1');
        $d2 = new ConcreteDefinitionNode('test.merge.d2');

        $graph2 = new Graph();
        $graph2->vertexStore->addVertex($c1);
        $graph2->vertexStore->addVertex($c2);
        $graph2->vertexStore->addVertex($c3);
        $graph2->vertexStore->addVertex($d1);
        $graph2->vertexStore->addVertex($d2);
        $graph2->edgeStore->addEdge(Transition::create($c1->id, $d1->id));
        $graph2->edgeStore->addEdge(Transition::create($c2->id, $d1->id));
        $graph2->edgeStore->addEdge(Transition::create($c2->id, $d2->id));
        $graph2->edgeStore->addEdge(Transition::create($c3->id, $d2->id));

        $def2 = SubGraphDefinition::create(
            name: 'test.merge.def2',
            graph: $graph2,
            inputs: [
                'in4' => ['node' => $c1],
                'in5' => ['node' => $c2],
                'in6' => ['node' => $c3],
            ],
            outputs: [
                'out3' => ['node' => $d1],
                'out4' => ['node' => $d2],
            ],
        );

        // def1.out1 -> def2.in4, def1.out2 -> def2.in5 (connected internally via outputPortMapping)
        // remaining empty inputs: def1.in1, def1.in2, def1.in3, def2.in6 -> 4 wrapper inputs
        // remaining empty outputs: def2.out3, def2.out4 -> 2 wrapper outputs
        $merged = SubGraphDefinition::merge(
            name: 'test.merge.merged',
            first: $def1,
            second: $def2,
            inputPortMapping: [],
            outputPortMapping: ['out1' => 'in4', 'out2' => 'in5'],
        );

        [$nodes] = $merged->getDefinedNodesAndTransitions();

        // 10 real nodes (a1-a3, b1-b2, c1-c3, d1-d2) + 2 passthrough nodes connecting out1->in4, out2->in5
        $this->assertCount(12, $nodes);

        // wrapper must expose 4 inputs (in1,in2,in3 from def1 + in6 from def2) and 2 outputs (out3,out4 from def2)
        $this->assertCount(4, (array) $merged->input);
        $this->assertCount(2, (array) $merged->output);
    }
}

class ConcreteDefinition extends Definition
{
    public function __construct(string $name = 'test.definition')
    {
        parent::__construct($name, new stdClass(), new stdClass());
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

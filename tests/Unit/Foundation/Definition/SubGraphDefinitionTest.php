<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Definition;

use PhpArchitecture\Graph\Graph;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Definition\SubGraphDefinition;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\Transition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SubGraphDefinitionTest extends TestCase
{
    private function makeNode(string $name): ConcreteDefinitionNode
    {
        return new ConcreteDefinitionNode($name);
    }

    private function makeGraph(array $nodes, array $edges): Graph
    {
        $graph = new Graph();
        foreach ($nodes as $node) {
            $graph->vertexStore->addVertex($node);
        }
        foreach ($edges as [$from, $to]) {
            $graph->edgeStore->addEdge(Transition::create($from->id, $to->id));
        }

        return $graph;
    }

    #[Test]
    public function createCopiesNodesAndTransitionsFromGraph(): void
    {
        $a = $this->makeNode('test.subgraph.a');
        $b = $this->makeNode('test.subgraph.b');
        $graph = $this->makeGraph([$a, $b], [[$a, $b]]);

        $def = SubGraphDefinition::create(
            name: 'test.subgraph.def',
            graph: $graph,
            inputs: ['in1' => ['node' => $a]],
            outputs: ['out1' => ['node' => $b]],
        );

        $def->input->in1->attach($a->id());
        $def->output->out1->attach($b->id());

        [$nodes, $transitions] = $def->getDefinedNodesAndTransitions();

        $this->assertCount(2, $nodes);
        $this->assertCount(3, $transitions); // a->b + port->a + b->port
    }

    #[Test]
    public function createExposesCorrectInputAndOutputPorts(): void
    {
        $a = $this->makeNode('test.subgraph.ports.a');
        $b = $this->makeNode('test.subgraph.ports.b');
        $graph = $this->makeGraph([$a, $b], [[$a, $b]]);

        $def = SubGraphDefinition::create(
            name: 'test.subgraph.ports.def',
            graph: $graph,
            inputs: ['in1' => ['node' => $a], 'in2' => ['node' => $a]],
            outputs: ['out1' => ['node' => $b]],
        );

        $this->assertCount(2, (array) $def->input);
        $this->assertCount(1, (array) $def->output);

        foreach ((array) $def->input as $port) {
            $this->assertInstanceOf(Port::class, $port);
        }
    }

    #[Test]
    public function createConnectsInputPortToCorrectNode(): void
    {
        $a = $this->makeNode('test.subgraph.connect.a');
        $b = $this->makeNode('test.subgraph.connect.b');
        $graph = $this->makeGraph([$a, $b], [[$a, $b]]);

        $def = SubGraphDefinition::create(
            name: 'test.subgraph.connect.def',
            graph: $graph,
            inputs: ['in1' => ['node' => $a]],
            outputs: ['out1' => ['node' => $b]],
        );

        $def->input->in1->attach($a->id());
        $def->output->out1->attach($b->id());

        [, $transitions] = $def->getDefinedNodesAndTransitions();

        $fromPort = array_values(array_filter(
            $transitions,
            static fn($tr) => $tr->v()->equals($a->id()),
        ));

        $this->assertCount(1, $fromPort);
    }

    #[Test]
    public function createSupportsNodeIdInsteadOfNodeInterface(): void
    {
        $a = $this->makeNode('test.subgraph.nodeid.a');
        $b = $this->makeNode('test.subgraph.nodeid.b');
        $graph = $this->makeGraph([$a, $b], [[$a, $b]]);

        $def = SubGraphDefinition::create(
            name: 'test.subgraph.nodeid.def',
            graph: $graph,
            inputs: ['in1' => ['node' => $a->id()]],
            outputs: ['out1' => ['node' => $b->id()]],
        );

        $def->input->in1->attach($a->id());
        $def->output->out1->attach($b->id());

        [$nodes] = $def->getDefinedNodesAndTransitions();

        $nodeNames = array_map(static fn(NodeInterface $n): string => $n->name(), $nodes);
        $this->assertContains('test.subgraph.nodeid.a', $nodeNames);
        $this->assertContains('test.subgraph.nodeid.b', $nodeNames);
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Definition;

use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Definition\SingleNodeDefinition;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SingleNodeDefinitionTest extends TestCase
{
    #[Test]
    public function createProducesDefinitionWithCorrectPortCount(): void
    {
        $node = new ConcreteDefinitionNode('test.single-node.node');
        $definition = SingleNodeDefinition::create($node, ['in1', 'in2'], ['out1']);

        $this->assertCount(2, (array) $definition->input);
        $this->assertCount(1, (array) $definition->output);
    }

    #[Test]
    public function createUsesNodeNameAsDefinitionName(): void
    {
        $node = new ConcreteDefinitionNode('test.single-node.named');
        $definition = SingleNodeDefinition::create($node, ['in1'], ['out1']);

        [$nodes] = $definition->getDefinedNodesAndTransitions();

        $nodeNames = array_map(static fn(NodeInterface $n): string => $n->name(), $nodes);
        $this->assertContains('test.single-node.named', $nodeNames);
    }

    #[Test]
    public function createAddsTransitionsFromInputPortsToNode(): void
    {
        $node = new ConcreteDefinitionNode('test.single-node.transitions');
        $definition = SingleNodeDefinition::create($node, ['in1', 'in2'], ['out1']);

        $definition->input->in1->attach($node->id());
        $definition->input->in2->attach($node->id());
        $definition->output->out1->attach($node->id());

        [, $transitions] = $definition->getDefinedNodesAndTransitions();

        $this->assertCount(3, $transitions);
    }

    #[Test]
    public function inputPortsArePortInstances(): void
    {
        $node = new ConcreteDefinitionNode('test.single-node.ports');
        $definition = SingleNodeDefinition::create($node, ['in1'], ['out1']);

        foreach ((array) $definition->input as $port) {
            $this->assertInstanceOf(Port::class, $port);
        }

        foreach ((array) $definition->output as $port) {
            $this->assertInstanceOf(Port::class, $port);
        }
    }
}

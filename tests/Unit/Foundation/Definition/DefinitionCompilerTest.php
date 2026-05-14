<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Definition;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Definition\DefinitionCompiler;
use PhpArchitecture\StateMachine\Foundation\Definition\Exception\CircularPortAttachmentException;
use PhpArchitecture\StateMachine\Foundation\Definition\Exception\OrphanNodeException;
use PhpArchitecture\StateMachine\Foundation\Definition\Port;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use LogicException;

class DefinitionCompilerTest extends TestCase
{
    private DefinitionCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new DefinitionCompiler();
    }

    private function makeDefinition(): DefinitionCompilerTestConcreteDefinition
    {
        return DefinitionCompilerTestConcreteDefinition::withPorts('test.definition', [], []);
    }

    private function makeNode(string $name): \PhpArchitecture\StateMachine\Tests\Unit\Foundation\Definition\ConcreteDefinitionNode
    {
        return new ConcreteDefinitionNode($name);
    }

    #[Test]
    public function compileReturnsRegularNodesAsDefinedNodes(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $nodeB = $this->makeNode('state-machine.definition.node-b');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($nodeB);

        [$nodes] = $this->compiler->compile($definition);

        $this->assertCount(2, $nodes);
    }

    #[Test]
    public function compileExcludesPortsFromDefinedNodes(): void
    {
        $definition = $this->makeDefinition();
        $regularNode = $this->makeNode('state-machine.definition.regular');
        $definition->addNodePublic($regularNode);

        $port = new Port('state-machine.definition.port.input');
        $port->attach($regularNode->id());
        $definition->addNodePublic($port);

        [$nodes] = $this->compiler->compile($definition);

        foreach ($nodes as $node) {
            $this->assertNotInstanceOf(Port::class, $node);
        }
    }

    #[Test]
    public function compileRemovesTransitionsOfUnattachedPort(): void
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

        [, $transitions] = $this->compiler->compile($definition);

        $this->assertEmpty($transitions);
    }

    #[Test]
    public function compileDoesNotRemoveUnrelatedTransitionsWhenPortUnattached(): void
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

        [, $transitions] = $this->compiler->compile($definition);

        $this->assertCount(1, $transitions);
        $this->assertTrue($nodeA->id()->equals(array_values($transitions)[0]->u()));
        $this->assertTrue($nodeB->id()->equals(array_values($transitions)[0]->v()));
    }

    #[Test]
    public function compileReplacesPortAsSourceWithAttachedNodeId(): void
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

        [, $transitions] = $this->compiler->compile($definition);

        $transition = array_values($transitions)[0];
        $this->assertTrue($externalNode->id()->equals($transition->u()));
        $this->assertTrue($nodeA->id()->equals($transition->v()));
    }

    #[Test]
    public function compileReplacesPortAsTargetWithAttachedNodeId(): void
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

        [, $transitions] = $this->compiler->compile($definition);

        $transition = array_values($transitions)[0];
        $this->assertTrue($nodeA->id()->equals($transition->u()));
        $this->assertTrue($externalNode->id()->equals($transition->v()));
    }

    #[Test]
    public function compileHandlesExternalAttachedNodeId(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $externalNode = $this->makeNode('state-machine.definition.external');
        $definition->addNodePublic($nodeA);

        $port = new Port('state-machine.definition.port.boundary');
        $port->attach($externalNode->id());
        $definition->addNodePublic($port);

        $definition->addTransitionPublic($nodeA->id(), $port->id());

        [$nodes, $transitions] = $this->compiler->compile($definition);

        $this->assertCount(1, $nodes);
        $this->assertCount(1, $transitions);
    }

    #[Test]
    public function compileHandlesMixedAttachedAndUnattachedPorts(): void
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

        [$nodes, $transitions] = $this->compiler->compile($definition);

        $this->assertCount(2, $nodes);
        $this->assertCount(2, $transitions);
    }

    #[Test]
    public function compileReturnsEmptyArraysForEmptyDefinition(): void
    {
        $definition = $this->makeDefinition();

        [$nodes, $transitions] = $this->compiler->compile($definition);

        $this->assertEmpty($nodes);
        $this->assertEmpty($transitions);
    }

    #[Test]
    public function compilePreservesRegularTransitionsWithoutPorts(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $nodeB = $this->makeNode('state-machine.definition.node-b');
        $nodeC = $this->makeNode('state-machine.definition.node-c');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($nodeB);
        $definition->addNodePublic($nodeC);

        $definition->addTransitionPublic($nodeA->id(), $nodeB->id());
        $definition->addTransitionPublic($nodeB->id(), $nodeC->id());

        [$nodes, $transitions] = $this->compiler->compile($definition);

        $this->assertCount(3, $nodes);
        $this->assertCount(2, $transitions);
    }

    #[Test]
    public function compileResultMatchesGetDefinedNodesAndTransitions(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $nodeB = $this->makeNode('state-machine.definition.node-b');
        $externalNode = $this->makeNode('state-machine.definition.external');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($nodeB);
        $definition->addNodePublic($externalNode);

        $attachedPort = new Port('state-machine.definition.port.attached');
        $attachedPort->attach($externalNode->id());
        $definition->addNodePublic($attachedPort);

        $unattachedPort = new Port('state-machine.definition.port.unattached');
        $definition->addNodePublic($unattachedPort);

        $definition->addTransitionPublic($nodeA->id(), $nodeB->id());
        $definition->addTransitionPublic($nodeA->id(), $attachedPort->id());
        $definition->addTransitionPublic($unattachedPort->id(), $nodeB->id());

        [$compilerNodes, $compilerTransitions] = $this->compiler->compile($definition);
        [$methodNodes, $methodTransitions] = $definition->getDefinedNodesAndTransitions();

        $this->assertCount(count($methodNodes), $compilerNodes);
        $this->assertCount(count($methodTransitions), $compilerTransitions);
    }

    #[Test]
    public function compileResolvesNestedPortAttachment(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $targetNode = $this->makeNode('state-machine.definition.target');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($targetNode);

        $outerPort = new Port('state-machine.definition.port.outer');
        $innerPort = new Port('state-machine.definition.port.inner');

        $innerPort->attach($targetNode->id());
        $outerPort->attach($innerPort);

        $definition->addNodePublic($outerPort);
        $definition->addNodePublic($innerPort);

        $definition->addTransitionPublic($nodeA->id(), $outerPort->id());

        [, $transitions] = $this->compiler->compile($definition);

        $this->assertCount(1, $transitions);
        $transition = array_values($transitions)[0];
        $this->assertTrue($nodeA->id()->equals($transition->u()));
        $this->assertTrue($targetNode->id()->equals($transition->v()));
    }

    #[Test]
    public function compileResolvesDeeplyNestedPortAttachment(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $targetNode = $this->makeNode('state-machine.definition.target');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($targetNode);

        $port1 = new Port('state-machine.definition.port.1');
        $port2 = new Port('state-machine.definition.port.2');
        $port3 = new Port('state-machine.definition.port.3');

        $port3->attach($targetNode->id());
        $port2->attach($port3);
        $port1->attach($port2);

        $definition->addNodePublic($port1);
        $definition->addNodePublic($port2);
        $definition->addNodePublic($port3);

        $definition->addTransitionPublic($nodeA->id(), $port1->id());

        [, $transitions] = $this->compiler->compile($definition);

        $this->assertCount(1, $transitions);
        $transition = array_values($transitions)[0];
        $this->assertTrue($targetNode->id()->equals($transition->v()));
    }

    #[Test]
    public function compileRemovesAllPortsInNestedChainWhenIntermediatePortUnattached(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $nodeB = $this->makeNode('state-machine.definition.node-b');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($nodeB);

        $outerPort = new Port('state-machine.definition.port.outer');
        $innerPort = new Port('state-machine.definition.port.inner');

        $outerPort->attach($innerPort);
        // innerPort remains unattached (attachedNode is null)

        $definition->addNodePublic($outerPort);
        $definition->addNodePublic($innerPort);

        $definition->addTransitionPublic($nodeA->id(), $outerPort->id());

        [, $transitions] = $this->compiler->compile($definition);

        $this->assertEmpty($transitions);
    }

    #[Test]
    public function compileResolvesNestedPortAttachmentViaNodeId(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $targetNode = $this->makeNode('state-machine.definition.target');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($targetNode);

        $outerPort = new Port('state-machine.definition.port.outer');
        $innerPort = new Port('state-machine.definition.port.inner');

        $innerPort->attach($targetNode->id());
        $outerPort->attach($innerPort->id());

        $definition->addNodePublic($outerPort);
        $definition->addNodePublic($innerPort);

        $definition->addTransitionPublic($nodeA->id(), $outerPort->id());

        [, $transitions] = $this->compiler->compile($definition);

        $this->assertCount(1, $transitions);
        $transition = array_values($transitions)[0];
        $this->assertTrue($nodeA->id()->equals($transition->u()));
        $this->assertTrue($targetNode->id()->equals($transition->v()));
    }

    #[Test]
    public function compileResolvesDeeplyNestedPortAttachmentViaNodeId(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $targetNode = $this->makeNode('state-machine.definition.target');
        $definition->addNodePublic($nodeA);
        $definition->addNodePublic($targetNode);

        $port1 = new Port('state-machine.definition.port.1');
        $port2 = new Port('state-machine.definition.port.2');
        $port3 = new Port('state-machine.definition.port.3');

        $port3->attach($targetNode->id());
        $port2->attach($port3->id());
        $port1->attach($port2->id());

        $definition->addNodePublic($port1);
        $definition->addNodePublic($port2);
        $definition->addNodePublic($port3);

        $definition->addTransitionPublic($nodeA->id(), $port1->id());

        [, $transitions] = $this->compiler->compile($definition);

        $this->assertCount(1, $transitions);
        $transition = array_values($transitions)[0];
        $this->assertTrue($targetNode->id()->equals($transition->v()));
    }

    #[Test]
    public function compileRemovesTransitionWhenPortAttachedViaNodeIdToUnattachedPort(): void
    {
        $definition = $this->makeDefinition();
        $nodeA = $this->makeNode('state-machine.definition.node-a');
        $definition->addNodePublic($nodeA);

        $outerPort = new Port('state-machine.definition.port.outer');
        $innerPort = new Port('state-machine.definition.port.inner');

        $outerPort->attach($innerPort->id());
        // innerPort remains unattached (attachedNode is null)

        $definition->addNodePublic($outerPort);
        $definition->addNodePublic($innerPort);

        $definition->addTransitionPublic($nodeA->id(), $outerPort->id());

        [, $transitions] = $this->compiler->compile($definition);

        $this->assertEmpty($transitions);
    }

    #[Test]
    public function compileThrowsOnCircularPortAttachmentViaNodeId(): void
    {
        $definition = $this->makeDefinition();

        $portA = new Port('state-machine.definition.port.a');
        $portB = new Port('state-machine.definition.port.b');

        $portA->attach($portB->id());
        $portB->attach($portA->id());

        $definition->addNodePublic($portA);
        $definition->addNodePublic($portB);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Circular port attachment detected');

        $this->compiler->compile($definition);
    }

    #[Test]
    public function compileThrowsOnCircularPortAttachment(): void
    {
        $definition = $this->makeDefinition();

        $portA = new Port('state-machine.definition.port.a');
        $portB = new Port('state-machine.definition.port.b');

        $portA->attach($portB);
        $portB->attach($portA);

        $definition->addNodePublic($portA);
        $definition->addNodePublic($portB);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Circular port attachment detected');

        $this->compiler->compile($definition);
    }
}

class DefinitionCompilerTestConcreteDefinition extends Definition
{
    /**
     * @param string[] $inputs
     * @param string[] $outputs
     */
    public static function withPorts(string $name, array $inputs, array $outputs): static
    {
        return static::newInstance($name, $inputs, $outputs);
    }

    public function addNodePublic(NodeInterface $node): static
    {
        return $this->addNode($node);
    }

    public function addTransitionPublic(NodeId $input, NodeId $output): static
    {
        return $this->addTransition($input, $output);
    }
}

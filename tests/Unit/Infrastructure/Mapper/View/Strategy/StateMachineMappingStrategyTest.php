<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Infrastructure\Mapper\View\Strategy;

use PhpArchitecture\Graph\Graph;
use PhpArchitecture\Graph\Vertex\Vertex;
use PhpArchitecture\StateMachine\Foundation\Config\StateMachineConfig;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Exception\UnsupportedResourceException;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy\NodeMappingStrategy;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy\StateMachineMappingStrategy;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy\TransitionMappingStrategy;
use PhpArchitecture\StateMachine\Presentation\View\NodeView;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

class StateMachineMappingStrategyTest extends TestCase
{
    private function makeStrategy(): StateMachineMappingStrategy
    {
        return new StateMachineMappingStrategy(new NodeMappingStrategy(), new TransitionMappingStrategy());
    }

    #[Test]
    public function mapsNodesAndTransitionsOfTheMachine(): void
    {
        $machine = new MappingStrategyTestMachine($this->createStub(ContainerInterface::class));
        $a = new PassthroughNode('state-machine.test.mapping.a');
        $b = new PassthroughNode('state-machine.test.mapping.b');
        $machine->add($a)->add($b)->addTransition($a->id(), $b->id());

        $view = $this->makeStrategy()->mapToView($machine);

        $this->assertSame(MappingStrategyTestMachine::class, $view->class);
        $this->assertSame(
            ['state-machine.test.mapping.a', 'state-machine.test.mapping.b'],
            array_map(static fn(NodeView $node): string => $node->globallyUniqueName, $view->nodes),
        );
        $this->assertCount(1, $view->transitions);
        $this->assertSame($a->id()->toString(), $view->transitions[0]->from);
        $this->assertSame($b->id()->toString(), $view->transitions[0]->to);
    }

    #[Test]
    public function verticesThatAreNotNodesAreNotMapped(): void
    {
        $graph = new Graph((new StateMachineConfig())->toGraphConfig());
        $graph->vertexStore->addVertex(new Vertex(NodeId::create('state-machine.test.mapping.ghost')));
        $machine = new MappingStrategyTestMachine($this->createStub(ContainerInterface::class), $graph);
        $machine->add(new PassthroughNode('state-machine.test.mapping.real'));

        $view = $this->makeStrategy()->mapToView($machine);

        $this->assertCount(1, $view->nodes);
        $this->assertSame([], $view->unknownVertices);
        $this->assertSame([], $view->unknownEdges);
    }

    #[Test]
    public function rejectsResourcesOtherThanStateMachine(): void
    {
        $this->expectException(UnsupportedResourceException::class);

        $this->makeStrategy()->mapToView(new stdClass());
    }
}

final class MappingStrategyTestMachine extends StateMachine
{
    public function add(PassthroughNode $node): static
    {
        return $this->addNode($node);
    }
}

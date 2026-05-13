<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Foundation\Component\AwaitAll;

use PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\AwaitAllComponent;
use PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node\AwaitAllArrivalNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Component\Fork\ForkComponent;
use PhpArchitecture\StateMachine\Foundation\Component\RaceFirst\Node\RaceConditionNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\ExecutionStatus;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

class AwaitAllFeatureTest extends TestCase
{
    private function makeContainer(): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(static function (string $class): object {
            return match ($class) {
                PassthroughNodeHandler::class     => new PassthroughNodeHandler(),
                AwaitAllArrivalNodeHandler::class => new AwaitAllArrivalNodeHandler(),
                AwaitAllFeatureNodeHandler::class => new AwaitAllFeatureNodeHandler(),
                RaceConditionNodeHandler::class   => new RaceConditionNodeHandler(),
                default => throw new RuntimeException("Unexpected handler: $class"),
            };
        });

        return $container;
    }

    #[Test]
    public function joinCompletesAfterAllBranchesArrive(): void
    {
        $startName = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node1";
        $midAName  = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node2";
        $midBName  = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node3";
        $endName   = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node4";
        $startId = NodeId::create($startName);
        $midAId  = NodeId::create($midAName);
        $midBId  = NodeId::create($midBName);
        $endId   = NodeId::create($endName);

        $fork = ForkComponent::create('fork-test', ['a', 'b']);
        $fork->input->trigger->attach($startId);
        $fork->output->a->attach($midAId);
        $fork->output->b->attach($midBId);

        $join = AwaitAllComponent::create('join-test', ['a', 'b']);
        $join->input->a->attach($midAId);
        $join->input->b->attach($midBId);
        $join->output->done->attach($endId);

        $machine = new AwaitAllFeatureMachine($this->makeContainer());
        foreach ([$startName, $midAName, $midBName, $endName] as $name) {
            $machine->addNodePublic(new AwaitAllFeatureNode($name));
        }
        $machine->addDefinition($fork);
        $machine->addDefinition($join);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);

        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Completed, $status);
    }

    #[Test]
    public function joinSuspendsWhenOnlyOneBranchArrives(): void
    {
        $midAName  = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node6";
        $midBName  = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node7";
        $endName   = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node8";
        $midAId  = NodeId::create($midAName);
        $midBId  = NodeId::create($midBName);
        $endId   = NodeId::create($endName);

        $join = AwaitAllComponent::create('join-test', ['a', 'b']);
        $join->input->a->attach($midAId);
        $join->input->b->attach($midBId);
        $join->output->done->attach($endId);

        $machine = new AwaitAllFeatureMachine($this->makeContainer());
        foreach ([$midAName, $midBName, $endName] as $name) {
            $machine->addNodePublic(new AwaitAllFeatureNode($name));
        }
        $machine->addDefinition($join);

        $execution = Execution::create();
        $execution->pointers->startAt($midAId);

        $machine->execute($execution);
        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Suspended, $status);
    }

    #[Test]
    public function joinWithThreeBranchesCompletesOnlyAfterAllThreeArrive(): void
    {
        $startName = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node9";
        $midAName  = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node10";
        $midBName  = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node11";
        $midCName  = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node12";
        $endName   = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node13";
        $startId = NodeId::create($startName);
        $midAId  = NodeId::create($midAName);
        $midBId  = NodeId::create($midBName);
        $midCId  = NodeId::create($midCName);
        $endId   = NodeId::create($endName);

        $fork = ForkComponent::create('fork-test', ['a', 'b', 'c']);
        $fork->input->trigger->attach($startId);
        $fork->output->a->attach($midAId);
        $fork->output->b->attach($midBId);
        $fork->output->c->attach($midCId);

        $join = AwaitAllComponent::create('join-test', ['a', 'b', 'c']);
        $join->input->a->attach($midAId);
        $join->input->b->attach($midBId);
        $join->input->c->attach($midCId);
        $join->output->done->attach($endId);

        $machine = new AwaitAllFeatureMachine($this->makeContainer());
        foreach ([$startName, $midAName, $midBName, $midCName, $endName] as $name) {
            $machine->addNodePublic(new AwaitAllFeatureNode($name));
        }
        $machine->addDefinition($fork);
        $machine->addDefinition($join);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);

        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Completed, $status);
    }

    #[Test]
    public function joinRecordsAllArrivedBranchesInStates(): void
    {
        $startName = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node14";
        $midAName  = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node15";
        $midBName  = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node16";
        $endName   = "state-machine.feature.foundation.component.awaitall.awaitallfeat.node17";
        $startId = NodeId::create($startName);
        $midAId  = NodeId::create($midAName);
        $midBId  = NodeId::create($midBName);
        $endId   = NodeId::create($endName);

        $fork = ForkComponent::create('fork-test', ['a', 'b']);
        $fork->input->trigger->attach($startId);
        $fork->output->a->attach($midAId);
        $fork->output->b->attach($midBId);

        $join = AwaitAllComponent::create('join-test', ['a', 'b']);
        $join->input->a->attach($midAId);
        $join->input->b->attach($midBId);
        $join->output->done->attach($endId);

        $machine = new AwaitAllFeatureMachine($this->makeContainer());
        foreach ([$startName, $midAName, $midBName, $endName] as $name) {
            $machine->addNodePublic(new AwaitAllFeatureNode($name));
        }
        $machine->addDefinition($fork);
        $machine->addDefinition($join);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);
        $machine->execute($execution);

        // Nowa implementacja zapisuje arrival info w stanie technicznym jako detale
        $techState = $execution->states->getTechnicalState();

        // Sprawdź czy są zapisy dla obu branchy (klucze zawierają '.a.arrived' i '.b.arrived')
        $hasBranchA = false;
        $hasBranchB = false;
        foreach ($techState->details as $detailName => $detail) {
            if (str_ends_with($detailName, '.a.arrived')) {
                $hasBranchA = true;
            }
            if (str_ends_with($detailName, '.b.arrived')) {
                $hasBranchB = true;
            }
        }

        $this->assertTrue($hasBranchA, 'Branch A arrival should be recorded in technical state');
        $this->assertTrue($hasBranchB, 'Branch B arrival should be recorded in technical state');
    }
}

class AwaitAllFeatureMachine extends StateMachine
{
    public function addNodePublic(\PhpArchitecture\StateMachine\Foundation\Node\NodeInterface $node): static
    {
        return $this->addNode($node);
    }
}

class AwaitAllFeatureNode extends Node
{
    public function handlerClass(): string
    {
        return AwaitAllFeatureNodeHandler::class;
    }
}

class AwaitAllFeatureNodeHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        return NodeHandlerResult::Continue;
    }
}

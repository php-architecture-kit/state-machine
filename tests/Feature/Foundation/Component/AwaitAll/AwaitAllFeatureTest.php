<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Foundation\Component\AwaitAll;

use PhpArchitecture\StateMachine\Foundation\Component\Parallel\ParallelComponent;
use PhpArchitecture\StateMachine\Foundation\Component\Parallel\Node\ParallelNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\AwaitAllComponent;
use PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node\AwaitAllArrivalNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node\AwaitAllSyncNodeHandler;
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
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(static function (string $class): object {
            return match ($class) {
                ParallelNodeHandler::class        => new ParallelNodeHandler(),
                AwaitAllArrivalNodeHandler::class => new AwaitAllArrivalNodeHandler(),
                AwaitAllSyncNodeHandler::class    => new AwaitAllSyncNodeHandler(),
                AwaitAllFeatureNodeHandler::class => new AwaitAllFeatureNodeHandler(),
                default => throw new RuntimeException("Unexpected handler: $class"),
            };
        });

        return $container;
    }

    #[Test]
    public function joinCompletesAfterAllBranchesArrive(): void
    {
        $names = [
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node1',
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node2',
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node3',
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node4',
        ];
        $startId = NodeId::create($names[0]);
        $midAId  = NodeId::create($names[1]);
        $midBId  = NodeId::create($names[2]);
        $endId   = NodeId::create($names[3]);

        $fork = ParallelComponent::create(['a', 'b']);
        $fork->input->trigger->attach($startId);
        $fork->output->a->attach($midAId);
        $fork->output->b->attach($midBId);

        $join = AwaitAllComponent::create(['a', 'b']);
        $join->input->a->attach($midAId);
        $join->input->b->attach($midBId);
        $join->output->done->attach($endId);

        $machine = new AwaitAllFeatureMachine($this->makeContainer());
        foreach ($names as $name) {
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
        $names = [
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node5',
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node6',
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node7',
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node8',
        ];
        $startId = NodeId::create($names[0]);
        $midAId  = NodeId::create($names[1]);
        $midBId  = NodeId::create($names[2]);
        $endId   = NodeId::create($names[3]);

        $join = AwaitAllComponent::create(['a', 'b']);
        $join->input->a->attach($midAId);
        $join->input->b->attach($midBId);
        $join->output->done->attach($endId);

        $machine = new AwaitAllFeatureMachine($this->makeContainer());
        foreach ($names as $name) {
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
        $names = [
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node9',
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node10',
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node11',
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node12',
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node13',
        ];
        $startId = NodeId::create($names[0]);
        $midAId  = NodeId::create($names[1]);
        $midBId  = NodeId::create($names[2]);
        $midCId  = NodeId::create($names[3]);
        $endId   = NodeId::create($names[4]);

        $fork = ParallelComponent::create(['a', 'b', 'c']);
        $fork->input->trigger->attach($startId);
        $fork->output->a->attach($midAId);
        $fork->output->b->attach($midBId);
        $fork->output->c->attach($midCId);

        $join = AwaitAllComponent::create(['a', 'b', 'c']);
        $join->input->a->attach($midAId);
        $join->input->b->attach($midBId);
        $join->input->c->attach($midCId);
        $join->output->done->attach($endId);

        $machine = new AwaitAllFeatureMachine($this->makeContainer());
        foreach ($names as $name) {
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
        $names = [
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node14',
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node15',
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node16',
            'state-machine.feature.foundation.component.awaitall.awaitallfeat.node17',
        ];
        $startId = NodeId::create($names[0]);
        $midAId  = NodeId::create($names[1]);
        $midBId  = NodeId::create($names[2]);
        $endId   = NodeId::create($names[3]);

        $fork = ParallelComponent::create(['a', 'b']);
        $fork->input->trigger->attach($startId);
        $fork->output->a->attach($midAId);
        $fork->output->b->attach($midBId);

        $join = AwaitAllComponent::create(['a', 'b']);
        $join->input->a->attach($midAId);
        $join->input->b->attach($midBId);
        $join->output->done->attach($endId);

        $machine = new AwaitAllFeatureMachine($this->makeContainer());
        foreach ($names as $name) {
            $machine->addNodePublic(new AwaitAllFeatureNode($name));
        }
        $machine->addDefinition($fork);
        $machine->addDefinition($join);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);
        $machine->execute($execution);

        $arrivedState = null;
        foreach ($execution->states->states as $state) {
            if (str_starts_with($state->name, 'join-arrived-')) {
                $arrivedState = $state;
                break;
            }
        }

        $this->assertNotNull($arrivedState);
        $this->assertArrayHasKey('a', $arrivedState->details);
        $this->assertArrayHasKey('b', $arrivedState->details);
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
    public function __construct(string $globallyUniqueName)
    {
        parent::__construct($globallyUniqueName);
    }

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

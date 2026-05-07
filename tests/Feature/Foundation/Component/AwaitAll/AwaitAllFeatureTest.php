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
        $startId = NodeId::new();
        $midAId  = NodeId::new();
        $midBId  = NodeId::new();
        $endId   = NodeId::new();

        $fork = ParallelComponent::create(['a', 'b']);
        $fork->input->trigger->attach($startId);
        $fork->output->a->attach($midAId);
        $fork->output->b->attach($midBId);

        $join = AwaitAllComponent::create(['a', 'b']);
        $join->input->a->attach($midAId);
        $join->input->b->attach($midBId);
        $join->output->done->attach($endId);

        $machine = new AwaitAllFeatureMachine($this->makeContainer());
        foreach ([$startId, $midAId, $midBId, $endId] as $nodeId) {
            $machine->addNodePublic(new AwaitAllFeatureNode($nodeId));
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
        $startId = NodeId::new();
        $midAId  = NodeId::new();
        $midBId  = NodeId::new();
        $endId   = NodeId::new();

        $join = AwaitAllComponent::create(['a', 'b']);
        $join->input->a->attach($midAId);
        $join->input->b->attach($midBId);
        $join->output->done->attach($endId);

        $machine = new AwaitAllFeatureMachine($this->makeContainer());
        foreach ([$startId, $midAId, $midBId, $endId] as $nodeId) {
            $machine->addNodePublic(new AwaitAllFeatureNode($nodeId));
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
        $startId = NodeId::new();
        $midAId  = NodeId::new();
        $midBId  = NodeId::new();
        $midCId  = NodeId::new();
        $endId   = NodeId::new();

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
        foreach ([$startId, $midAId, $midBId, $midCId, $endId] as $nodeId) {
            $machine->addNodePublic(new AwaitAllFeatureNode($nodeId));
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
        $startId = NodeId::new();
        $midAId  = NodeId::new();
        $midBId  = NodeId::new();
        $endId   = NodeId::new();

        $fork = ParallelComponent::create(['a', 'b']);
        $fork->input->trigger->attach($startId);
        $fork->output->a->attach($midAId);
        $fork->output->b->attach($midBId);

        $join = AwaitAllComponent::create(['a', 'b']);
        $join->input->a->attach($midAId);
        $join->input->b->attach($midBId);
        $join->output->done->attach($endId);

        $machine = new AwaitAllFeatureMachine($this->makeContainer());
        foreach ([$startId, $midAId, $midBId, $endId] as $nodeId) {
            $machine->addNodePublic(new AwaitAllFeatureNode($nodeId));
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

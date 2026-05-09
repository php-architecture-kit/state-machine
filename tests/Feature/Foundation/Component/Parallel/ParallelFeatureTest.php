<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Foundation\Component\Parallel;

use PhpArchitecture\StateMachine\Foundation\Component\Parallel\ParallelComponent;
use PhpArchitecture\StateMachine\Foundation\Component\Parallel\Node\ParallelNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\ExecutionStatus;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Pointer\Event\PointerForkedEvent;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

class ParallelFeatureTest extends TestCase
{
    private function makeContainer(): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(static function (string $class): object {
            return match ($class) {
                ParallelNodeHandler::class       => new ParallelNodeHandler(),
                ParallelFeatureNodeHandler::class => new ParallelFeatureNodeHandler(),
                default => throw new RuntimeException("Unexpected handler: $class"),
            };
        });

        return $container;
    }

    #[Test]
    public function forkSpawnsOnePointerPerBranch(): void
    {
        $startId = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node1");
        $endAId  = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node2");
        $endBId  = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node3");
        $endCId  = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node4");

        $component = ParallelComponent::create(['a', 'b', 'c']);
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);
        $component->output->c->attach($endCId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ([$startId, $endAId, $endBId, $endCId] as $nodeId) {
            $machine->addNodePublic(new ParallelFeatureNode($nodeId));
        }
        $machine->addDefinition($component);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);

        $machine->execute($execution);

        $forkedEvents = array_filter(
            $execution->pointers->getEvents(),
            static fn(object $e): bool => $e instanceof PointerForkedEvent,
        );
        $this->assertCount(3, $forkedEvents);
    }

    #[Test]
    public function forkCompletesWhenAllBranchesReachTerminalNodes(): void
    {
        $startId = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node5");
        $endAId  = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node6");
        $endBId  = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node7");

        $component = ParallelComponent::create(['a', 'b']);
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ([$startId, $endAId, $endBId] as $nodeId) {
            $machine->addNodePublic(new ParallelFeatureNode($nodeId));
        }
        $machine->addDefinition($component);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);

        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Completed, $status);
    }

    #[Test]
    public function originalPointerIsRemovedAfterFork(): void
    {
        $startId = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node8");
        $endAId  = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node9");
        $endBId  = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node10");

        $component = ParallelComponent::create(['a', 'b']);
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ([$startId, $endAId, $endBId] as $nodeId) {
            $machine->addNodePublic(new ParallelFeatureNode($nodeId));
        }
        $machine->addDefinition($component);

        $execution = Execution::create();
        $originalPointer = $execution->pointers->startAt($startId);

        $machine->execute($execution);

        $this->assertArrayNotHasKey(
            $originalPointer->id->toString(),
            $execution->pointers->pointers,
        );
    }

    #[Test]
    public function twoBranchForkProducesExactlyTwoForkedEvents(): void
    {
        $startId = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node11");
        $endAId  = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node12");
        $endBId  = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node13");

        $component = ParallelComponent::create(['a', 'b']);
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ([$startId, $endAId, $endBId] as $nodeId) {
            $machine->addNodePublic(new ParallelFeatureNode($nodeId));
        }
        $machine->addDefinition($component);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);

        $machine->execute($execution);

        $forkedEvents = array_filter(
            $execution->pointers->getEvents(),
            static fn(object $e): bool => $e instanceof PointerForkedEvent,
        );
        $this->assertCount(2, $forkedEvents);
    }

    #[Test]
    public function conditionalBranchesOnlyFireWhenPredicateMatches(): void
    {
        $startId = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node14");
        $endAId  = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node15");
        $endBId  = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node16");
        $endCId  = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node17");

        $component = ParallelComponent::create(
            ['a', 'b', 'c'],
            [
                'b' => fn(States $s): bool => false,
                'c' => fn(States $s): bool => true,
            ],
        );
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);
        $component->output->c->attach($endCId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ([$startId, $endAId, $endBId, $endCId] as $nodeId) {
            $machine->addNodePublic(new ParallelFeatureNode($nodeId));
        }
        $machine->addDefinition($component);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);
        $machine->execute($execution);

        $forkedEvents = array_filter(
            $execution->pointers->getEvents(),
            static fn(object $e): bool => $e instanceof PointerForkedEvent,
        );
        $this->assertCount(2, $forkedEvents);
    }

    #[Test]
    public function conditionalBranchBasedOnStateFiresCorrectly(): void
    {
        $startId = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node18");
        $highId  = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node19");
        $lowId   = NodeId::create("state-machine.feature.foundation.component.parallel.parallelfeat.node20");

        $component = ParallelComponent::create(
            ['high', 'low'],
            [
                'high' => fn(States $s): bool => ($s->getState('order')?->details['amount']?->value ?? 0) > 1000,
                'low'  => fn(States $s): bool => ($s->getState('order')?->details['amount']?->value ?? 0) <= 1000,
            ],
        );
        $component->input->trigger->attach($startId);
        $component->output->high->attach($highId);
        $component->output->low->attach($lowId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ([$startId, $highId, $lowId] as $nodeId) {
            $machine->addNodePublic(new ParallelFeatureNode($nodeId));
        }
        $machine->addDefinition($component);

        $execution = Execution::create();
        $execution->states->defineState('order', [new StateDetail('amount', 5000)]);
        $execution->pointers->startAt($startId);
        $status = $machine->execute($execution);

        $forkedEvents = array_filter(
            $execution->pointers->getEvents(),
            static fn(object $e): bool => $e instanceof PointerForkedEvent,
        );
        $this->assertCount(0, $forkedEvents);
        $this->assertSame(ExecutionStatus::Completed, $status);
    }
}

class ParallelFeatureMachine extends StateMachine
{
    public function addNodePublic(\PhpArchitecture\StateMachine\Foundation\Node\NodeInterface $node): static
    {
        return $this->addNode($node);
    }
}

class ParallelFeatureNode extends Node
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
        return ParallelFeatureNodeHandler::class;
    }
}

class ParallelFeatureNodeHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        return NodeHandlerResult::Continue;
    }
}

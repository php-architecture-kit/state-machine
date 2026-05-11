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
        $names = [
            'state-machine.feature.foundation.component.parallel.parallelfeat.node1',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node2',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node3',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node4',
        ];
        $startId = NodeId::create($names[0]);
        $endAId  = NodeId::create($names[1]);
        $endBId  = NodeId::create($names[2]);
        $endCId  = NodeId::create($names[3]);

        $component = ParallelComponent::create(['a', 'b', 'c']);
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);
        $component->output->c->attach($endCId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ($names as $name) {
            $machine->addNodePublic(new ParallelFeatureNode($name));
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
        $names = [
            'state-machine.feature.foundation.component.parallel.parallelfeat.node5',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node6',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node7',
        ];
        $startId = NodeId::create($names[0]);
        $endAId  = NodeId::create($names[1]);
        $endBId  = NodeId::create($names[2]);

        $component = ParallelComponent::create(['a', 'b']);
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ($names as $name) {
            $machine->addNodePublic(new ParallelFeatureNode($name));
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
        $names = [
            'state-machine.feature.foundation.component.parallel.parallelfeat.node8',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node9',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node10',
        ];
        $startId = NodeId::create($names[0]);
        $endAId  = NodeId::create($names[1]);
        $endBId  = NodeId::create($names[2]);

        $component = ParallelComponent::create(['a', 'b']);
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ($names as $name) {
            $machine->addNodePublic(new ParallelFeatureNode($name));
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
        $names = [
            'state-machine.feature.foundation.component.parallel.parallelfeat.node11',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node12',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node13',
        ];
        $startId = NodeId::create($names[0]);
        $endAId  = NodeId::create($names[1]);
        $endBId  = NodeId::create($names[2]);

        $component = ParallelComponent::create(['a', 'b']);
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ($names as $name) {
            $machine->addNodePublic(new ParallelFeatureNode($name));
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
        $names = [
            'state-machine.feature.foundation.component.parallel.parallelfeat.node14',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node15',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node16',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node17',
        ];
        $startId = NodeId::create($names[0]);
        $endAId  = NodeId::create($names[1]);
        $endBId  = NodeId::create($names[2]);
        $endCId  = NodeId::create($names[3]);

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
        foreach ($names as $name) {
            $machine->addNodePublic(new ParallelFeatureNode($name));
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
        $names = [
            'state-machine.feature.foundation.component.parallel.parallelfeat.node18',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node19',
            'state-machine.feature.foundation.component.parallel.parallelfeat.node20',
        ];
        $startId = NodeId::create($names[0]);
        $highId  = NodeId::create($names[1]);
        $lowId   = NodeId::create($names[2]);

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
        foreach ($names as $name) {
            $machine->addNodePublic(new ParallelFeatureNode($name));
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
    public function __construct(string $globallyUniqueName)
    {
        parent::__construct($globallyUniqueName);
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

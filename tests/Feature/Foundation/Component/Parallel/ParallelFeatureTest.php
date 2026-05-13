<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Foundation\Component\Parallel;

use PhpArchitecture\StateMachine\Foundation\Component\Fork\ForkComponent;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNodeHandler;
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
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

class ParallelFeatureTest extends TestCase
{
    private function makeContainer(): ContainerInterface
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(static function (string $class): object {
            return match ($class) {
                PassthroughNodeHandler::class     => new PassthroughNodeHandler(),
                ParallelFeatureNodeHandler::class => new ParallelFeatureNodeHandler(),
                default => throw new RuntimeException("Unexpected handler: $class"),
            };
        });

        return $container;
    }

    #[Test]
    public function forkSpawnsOnePointerPerBranch(): void
    {
        $startName = "state-machine.feature.foundation.component.parallel.parallelfeat.node1";
        $endAName  = "state-machine.feature.foundation.component.parallel.parallelfeat.node2";
        $endBName  = "state-machine.feature.foundation.component.parallel.parallelfeat.node3";
        $endCName  = "state-machine.feature.foundation.component.parallel.parallelfeat.node4";
        $startId = NodeId::create($startName);
        $endAId  = NodeId::create($endAName);
        $endBId  = NodeId::create($endBName);
        $endCId  = NodeId::create($endCName);

        $component = ForkComponent::create('test-component', ['a', 'b', 'c']);
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);
        $component->output->c->attach($endCId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ([$startName, $endAName, $endBName, $endCName] as $name) {
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
        $startName = "state-machine.feature.foundation.component.parallel.parallelfeat.node5";
        $endAName  = "state-machine.feature.foundation.component.parallel.parallelfeat.node6";
        $endBName  = "state-machine.feature.foundation.component.parallel.parallelfeat.node7";
        $startId = NodeId::create($startName);
        $endAId  = NodeId::create($endAName);
        $endBId  = NodeId::create($endBName);

        $component = ForkComponent::create('test-component', ['a', 'b']);
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ([$startName, $endAName, $endBName] as $name) {
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
        $startName = "state-machine.feature.foundation.component.parallel.parallelfeat.node8";
        $endAName  = "state-machine.feature.foundation.component.parallel.parallelfeat.node9";
        $endBName  = "state-machine.feature.foundation.component.parallel.parallelfeat.node10";
        $startId = NodeId::create($startName);
        $endAId  = NodeId::create($endAName);
        $endBId  = NodeId::create($endBName);

        $component = ForkComponent::create('test-component', ['a', 'b']);
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ([$startName, $endAName, $endBName] as $name) {
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
        $startName = "state-machine.feature.foundation.component.parallel.parallelfeat.node11";
        $endAName  = "state-machine.feature.foundation.component.parallel.parallelfeat.node12";
        $endBName  = "state-machine.feature.foundation.component.parallel.parallelfeat.node13";
        $startId = NodeId::create($startName);
        $endAId  = NodeId::create($endAName);
        $endBId  = NodeId::create($endBName);

        $component = ForkComponent::create('test-component', ['a', 'b']);
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ([$startName, $endAName, $endBName] as $name) {
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
        $startName = "state-machine.feature.foundation.component.parallel.parallelfeat.node14";
        $endAName  = "state-machine.feature.foundation.component.parallel.parallelfeat.node15";
        $endBName  = "state-machine.feature.foundation.component.parallel.parallelfeat.node16";
        $endCName  = "state-machine.feature.foundation.component.parallel.parallelfeat.node17";
        $startId = NodeId::create($startName);
        $endAId  = NodeId::create($endAName);
        $endBId  = NodeId::create($endBName);
        $endCId  = NodeId::create($endCName);

        $component = ForkComponent::create(
            'test-component',
            ['a', 'b', 'c'],
            [
                'b' => fn(States $s): TransitionConditionDecision => TransitionConditionDecision::Rejected,
                'c' => fn(States $s): TransitionConditionDecision => TransitionConditionDecision::Accepted,
            ],
        );
        $component->input->trigger->attach($startId);
        $component->output->a->attach($endAId);
        $component->output->b->attach($endBId);
        $component->output->c->attach($endCId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ([$startName, $endAName, $endBName, $endCName] as $name) {
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
        $startName = "state-machine.feature.foundation.component.parallel.parallelfeat.node18";
        $highName  = "state-machine.feature.foundation.component.parallel.parallelfeat.node19";
        $lowName   = "state-machine.feature.foundation.component.parallel.parallelfeat.node20";
        $startId = NodeId::create($startName);
        $highId  = NodeId::create($highName);
        $lowId   = NodeId::create($lowName);

        $component = ForkComponent::create(
            'test-component',
            ['high', 'low'],
            [
                'high' => fn(States $s): TransitionConditionDecision => ($s->getState('order')?->details['amount']?->value ?? 0) > 1000 ? TransitionConditionDecision::Accepted : TransitionConditionDecision::Rejected,
                'low'  => fn(States $s): TransitionConditionDecision => ($s->getState('order')?->details['amount']?->value ?? 0) <= 1000 ? TransitionConditionDecision::Accepted : TransitionConditionDecision::Rejected,
            ],
        );
        $component->input->trigger->attach($startId);
        $component->output->high->attach($highId);
        $component->output->low->attach($lowId);

        $machine = new ParallelFeatureMachine($this->makeContainer());
        foreach ([$startName, $highName, $lowName] as $name) {
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

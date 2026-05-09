<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Foundation\Component\AwaitState;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PhpArchitecture\StateMachine\Foundation\Component\AwaitState\AwaitStateComponent;
use PhpArchitecture\StateMachine\Foundation\Component\AwaitState\Node\AwaitStateNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\ExecutionStatus;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

class AwaitStateFeatureTest extends TestCase
{
    private function makeMachine(ContainerInterface $container): AwaitStateFeatureMachine
    {
        return new AwaitStateFeatureMachine($container);
    }

    private function makeContainer(): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(static function (string $class): object {
            return match ($class) {
                AwaitStateNodeHandler::class    => new AwaitStateNodeHandler(),
                AwaitStateFeatureNodeHandler::class => new AwaitStateFeatureNodeHandler(),
                default => throw new RuntimeException("Unexpected handler: $class"),
            };
        });

        return $container;
    }

    #[Test]
    public function machineSuspendsWhileAwaitedStateIsAbsent(): void
    {
        $startNodeId = NodeId::create("state-machine.feature.foundation.component.awaitstate.awaitstate.node1");
        $endNodeId = NodeId::create("state-machine.feature.foundation.component.awaitstate.awaitstate.node2");
        $component = AwaitStateComponent::create('user_answer');

        $machine = $this->makeMachine($this->makeContainer());
        $machine->buildWithComponent($startNodeId, $endNodeId, $component);

        $execution = Execution::create();
        $execution->pointers->startAt($startNodeId);

        $machine->execute($execution);
        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Suspended, $status);
    }

    #[Test]
    public function machineCompletesAfterAwaitedStateIsDefined(): void
    {
        $startNodeId = NodeId::create("state-machine.feature.foundation.component.awaitstate.awaitstate.node3");
        $endNodeId = NodeId::create("state-machine.feature.foundation.component.awaitstate.awaitstate.node4");
        $component = AwaitStateComponent::create('user_answer');

        $machine = $this->makeMachine($this->makeContainer());
        $machine->buildWithComponent($startNodeId, $endNodeId, $component);

        $execution = Execution::create();
        $execution->pointers->startAt($startNodeId);

        $machine->execute($execution);

        $execution->states->defineState('user_answer', []);
        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Completed, $status);
    }

    #[Test]
    public function machineSuspendsWhenRequiredDetailMissing(): void
    {
        $startNodeId = NodeId::create("state-machine.feature.foundation.component.awaitstate.awaitstate.node5");
        $endNodeId = NodeId::create("state-machine.feature.foundation.component.awaitstate.awaitstate.node6");
        $component = AwaitStateComponent::create('user_answer', 'value');

        $machine = $this->makeMachine($this->makeContainer());
        $machine->buildWithComponent($startNodeId, $endNodeId, $component);

        $execution = Execution::create();
        $execution->pointers->startAt($startNodeId);

        $machine->execute($execution);

        $execution->states->defineState('user_answer', []);
        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Suspended, $status);
    }

    #[Test]
    public function machineCompletesWhenRequiredDetailIsPresent(): void
    {
        $startNodeId = NodeId::create("state-machine.feature.foundation.component.awaitstate.awaitstate.node7");
        $endNodeId = NodeId::create("state-machine.feature.foundation.component.awaitstate.awaitstate.node8");
        $component = AwaitStateComponent::create('user_answer', 'value');

        $machine = $this->makeMachine($this->makeContainer());
        $machine->buildWithComponent($startNodeId, $endNodeId, $component);

        $execution = Execution::create();
        $execution->pointers->startAt($startNodeId);

        $machine->execute($execution);

        $execution->states->defineState('user_answer', [new StateDetail('value', 'yes')]);
        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Completed, $status);
    }

    #[Test]
    public function pointerReachesExpiredNodeWhenTimeoutElapsed(): void
    {
        $startNodeId = NodeId::create("state-machine.feature.foundation.component.awaitstate.awaitstate.node9");
        $doneNodeId = NodeId::create("state-machine.feature.foundation.component.awaitstate.awaitstate.node10");
        $expiredNodeId = NodeId::create("state-machine.feature.foundation.component.awaitstate.awaitstate.node11");

        $start = new DateTimeImmutable('2025-01-01 12:00:00', new DateTimeZone('UTC'));
        $afterTimeout = $start->add(new DateInterval('PT61S'));

        $clock = $this->createMock(ClockInterface::class);
        $call = 0;
        $clock->method('now')->willReturnCallback(
            function () use ($start, $afterTimeout, &$call): DateTimeImmutable {
                $call++;
                return $call <= 3 ? $start : $afterTimeout;
            },
        );

        $component = AwaitStateComponent::create('user_answer', null, 60, $clock);

        $machine = $this->makeMachine($this->makeContainer());
        $machine->buildWithExpiredOutput($startNodeId, $doneNodeId, $expiredNodeId, $component);

        $execution = Execution::create();
        $execution->pointers->startAt($startNodeId);

        $machine->execute($execution);
        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Completed, $status);
    }

    #[Test]
    public function machineCompletesAfterMultipleSuspendsAndStateDefined(): void
    {
        $startNodeId = NodeId::create("state-machine.feature.foundation.component.awaitstate.awaitstate.node12");
        $endNodeId = NodeId::create("state-machine.feature.foundation.component.awaitstate.awaitstate.node13");
        $component = AwaitStateComponent::create('user_answer');

        $machine = $this->makeMachine($this->makeContainer());
        $machine->buildWithComponent($startNodeId, $endNodeId, $component);

        $execution = Execution::create();
        $execution->pointers->startAt($startNodeId);

        $machine->execute($execution);
        $machine->execute($execution);
        $machine->execute($execution);

        $execution->states->defineState('user_answer', []);
        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Completed, $status);
    }
}

class AwaitStateFeatureMachine extends StateMachine
{
    public function buildWithComponent(NodeId $startId, NodeId $endId, AwaitStateComponent $component): void
    {
        $startNode = new AwaitStateFeatureNode($startId);
        $endNode = new AwaitStateFeatureNode($endId);

        $component->input->trigger->attach($startId);
        $component->output->done->attach($endId);

        $this->graph->vertexStore->addVertex($startNode);
        $this->graph->vertexStore->addVertex($endNode);

        $this->addDefinition($component);
    }

    public function buildWithExpiredOutput(NodeId $startId, NodeId $doneId, NodeId $expiredId, AwaitStateComponent $component): void
    {
        $startNode = new AwaitStateFeatureNode($startId);
        $doneNode = new AwaitStateFeatureNode($doneId);
        $expiredNode = new AwaitStateFeatureNode($expiredId);

        $component->input->trigger->attach($startId);
        $component->output->done->attach($doneId);
        $component->output->expired->attach($expiredId);

        $this->graph->vertexStore->addVertex($startNode);
        $this->graph->vertexStore->addVertex($doneNode);
        $this->graph->vertexStore->addVertex($expiredNode);

        $this->addDefinition($component);
    }
}

class AwaitStateFeatureNode extends Node
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
        return AwaitStateFeatureNodeHandler::class;
    }
}

class AwaitStateFeatureNodeHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        return NodeHandlerResult::Continue;
    }
}

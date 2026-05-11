<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Foundation\Component\AwaitState;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PhpArchitecture\StateMachine\Foundation\Component\Await\AwaitStateComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNodeHandler;
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
                PassthroughNodeHandler::class       => new PassthroughNodeHandler(),
                AwaitStateFeatureNodeHandler::class => new AwaitStateFeatureNodeHandler(),
                default => throw new RuntimeException("Unexpected handler: $class"),
            };
        });

        return $container;
    }

    #[Test]
    public function machineSuspendsWhileAwaitedStateIsAbsent(): void
    {
        $startName = "state-machine.feature.foundation.component.awaitstate.awaitstate.node1";
        $endName = "state-machine.feature.foundation.component.awaitstate.awaitstate.node2";
        $component = AwaitStateComponent::create('user_answer', 'user_answer');

        $machine = $this->makeMachine($this->makeContainer());
        $machine->buildWithComponent($startName, $endName, $component);

        $execution = Execution::create();
        $execution->pointers->startAt(NodeId::create($startName));

        $machine->execute($execution);
        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Suspended, $status);
    }

    #[Test]
    public function machineCompletesAfterAwaitedStateIsDefined(): void
    {
        $startName = "state-machine.feature.foundation.component.awaitstate.awaitstate.node3";
        $endName = "state-machine.feature.foundation.component.awaitstate.awaitstate.node4";
        $component = AwaitStateComponent::create('user_answer', 'user_answer');

        $machine = $this->makeMachine($this->makeContainer());
        $machine->buildWithComponent($startName, $endName, $component);

        $execution = Execution::create();
        $execution->pointers->startAt(NodeId::create($startName));

        $machine->execute($execution);

        $execution->states->defineState('user_answer', []);
        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Completed, $status);
    }

    #[Test]
    public function machineSuspendsWhenRequiredDetailMissing(): void
    {
        $startName = "state-machine.feature.foundation.component.awaitstate.awaitstate.node5";
        $endName = "state-machine.feature.foundation.component.awaitstate.awaitstate.node6";
        $component = AwaitStateComponent::create('user_answer', 'user_answer', 'value');

        $machine = $this->makeMachine($this->makeContainer());
        $machine->buildWithComponent($startName, $endName, $component);

        $execution = Execution::create();
        $execution->pointers->startAt(NodeId::create($startName));

        $machine->execute($execution);

        $execution->states->defineState('user_answer', []);
        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Suspended, $status);
    }

    #[Test]
    public function machineCompletesWhenRequiredDetailIsPresent(): void
    {
        $startName = "state-machine.feature.foundation.component.awaitstate.awaitstate.node7";
        $endName = "state-machine.feature.foundation.component.awaitstate.awaitstate.node8";
        $component = AwaitStateComponent::create('user_answer', 'user_answer', 'value');

        $machine = $this->makeMachine($this->makeContainer());
        $machine->buildWithComponent($startName, $endName, $component);

        $execution = Execution::create();
        $execution->pointers->startAt(NodeId::create($startName));

        $machine->execute($execution);

        $execution->states->defineState('user_answer', [new StateDetail('value', 'yes')]);
        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Completed, $status);
    }

    #[Test]
    public function pointerReachesExpiredNodeWhenTimeoutElapsed(): void
    {
        $startName = "state-machine.feature.foundation.component.awaitstate.awaitstate.node9";
        $doneName = "state-machine.feature.foundation.component.awaitstate.awaitstate.node10";
        $expiredName = "state-machine.feature.foundation.component.awaitstate.awaitstate.node11";

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

        $component = AwaitStateComponent::create('user_answer', 'user_answer', null, 60, $clock);

        $machine = $this->makeMachine($this->makeContainer());
        $machine->buildWithExpiredOutput($startName, $doneName, $expiredName, $component);

        $execution = Execution::create();
        $execution->pointers->startAt(NodeId::create($startName));

        $machine->execute($execution);
        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Completed, $status);
    }

    #[Test]
    public function machineCompletesAfterMultipleSuspendsAndStateDefined(): void
    {
        $startName = "state-machine.feature.foundation.component.awaitstate.awaitstate.node12";
        $endName = "state-machine.feature.foundation.component.awaitstate.awaitstate.node13";
        $component = AwaitStateComponent::create('user_answer', 'user_answer');

        $machine = $this->makeMachine($this->makeContainer());
        $machine->buildWithComponent($startName, $endName, $component);

        $execution = Execution::create();
        $execution->pointers->startAt(NodeId::create($startName));

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
    public function buildWithComponent(string $startName, string $endName, Definition $component): void
    {
        $startId = NodeId::create($startName);
        $endId = NodeId::create($endName);

        $component->input->at->attach($startId);
        $component->output->run->attach($endId);

        $this->graph->vertexStore->addVertex(new AwaitStateFeatureNode($startName));
        $this->graph->vertexStore->addVertex(new AwaitStateFeatureNode($endName));

        $this->addDefinition($component);
    }

    public function buildWithExpiredOutput(string $startName, string $doneName, string $expiredName, Definition $component): void
    {
        $startId = NodeId::create($startName);
        $doneId = NodeId::create($doneName);
        $expiredId = NodeId::create($expiredName);

        $component->input->at->attach($startId);
        $component->output->run->attach($doneId);
        $component->output->expired->attach($expiredId);

        $this->graph->vertexStore->addVertex(new AwaitStateFeatureNode($startName));
        $this->graph->vertexStore->addVertex(new AwaitStateFeatureNode($doneName));
        $this->graph->vertexStore->addVertex(new AwaitStateFeatureNode($expiredName));

        $this->addDefinition($component);
    }
}

class AwaitStateFeatureNode extends Node
{
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

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
        $startName = 'state-machine.feature.foundation.component.awaitstate.awaitstate.node1';
        $endName = 'state-machine.feature.foundation.component.awaitstate.awaitstate.node2';
        $component = AwaitStateComponent::create('user_answer');

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
        $startName = 'state-machine.feature.foundation.component.awaitstate.awaitstate.node3';
        $endName = 'state-machine.feature.foundation.component.awaitstate.awaitstate.node4';
        $component = AwaitStateComponent::create('user_answer');

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
        $startName = 'state-machine.feature.foundation.component.awaitstate.awaitstate.node5';
        $endName = 'state-machine.feature.foundation.component.awaitstate.awaitstate.node6';
        $component = AwaitStateComponent::create('user_answer', 'value');

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
        $startName = 'state-machine.feature.foundation.component.awaitstate.awaitstate.node7';
        $endName = 'state-machine.feature.foundation.component.awaitstate.awaitstate.node8';
        $component = AwaitStateComponent::create('user_answer', 'value');

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
        $startName = 'state-machine.feature.foundation.component.awaitstate.awaitstate.node9';
        $doneName = 'state-machine.feature.foundation.component.awaitstate.awaitstate.node10';
        $expiredName = 'state-machine.feature.foundation.component.awaitstate.awaitstate.node11';

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
        $startName = 'state-machine.feature.foundation.component.awaitstate.awaitstate.node12';
        $endName = 'state-machine.feature.foundation.component.awaitstate.awaitstate.node13';
        $component = AwaitStateComponent::create('user_answer');

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
    public function buildWithComponent(string $startName, string $endName, AwaitStateComponent $component): void
    {
        $startNode = new AwaitStateFeatureNode($startName);
        $endNode = new AwaitStateFeatureNode($endName);

        $component->input->trigger->attach($startNode->id);
        $component->output->done->attach($endNode->id);

        $this->graph->vertexStore->addVertex($startNode);
        $this->graph->vertexStore->addVertex($endNode);

        $this->addDefinition($component);
    }

    public function buildWithExpiredOutput(string $startName, string $doneName, string $expiredName, AwaitStateComponent $component): void
    {
        $startNode = new AwaitStateFeatureNode($startName);
        $doneNode = new AwaitStateFeatureNode($doneName);
        $expiredNode = new AwaitStateFeatureNode($expiredName);

        $component->input->trigger->attach($startNode->id);
        $component->output->done->attach($doneNode->id);
        $component->output->expired->attach($expiredNode->id);

        $this->graph->vertexStore->addVertex($startNode);
        $this->graph->vertexStore->addVertex($doneNode);
        $this->graph->vertexStore->addVertex($expiredNode);

        $this->addDefinition($component);
    }
}

class AwaitStateFeatureNode extends Node
{
    public function __construct(string $globallyUniqueName)
    {
        parent::__construct($globallyUniqueName);
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

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Foundation\Component\Async;

use PhpArchitecture\StateMachine\Foundation\Component\Async\AsyncComponent;
use PhpArchitecture\StateMachine\Foundation\Component\Async\Node\AsyncNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\ExecutionStatus;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Stamp\AwaitStateStamp;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskBusInterface;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

class AsyncFeatureTest extends TestCase
{
    private TaskEnvelope|null $lastEnvelope = null;

    private function makeTaskBus(): TaskBusInterface
    {
        $taskBus = $this->createMock(TaskBusInterface::class);
        $taskBus->method('dispatch')->willReturnCallback(function (Task $task, array $stamps): TaskEnvelope {
            $envelope = TaskEnvelope::create($task, $stamps);
            $this->lastEnvelope = $envelope;
            return $envelope;
        });

        return $taskBus;
    }

    private function makeContainer(): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(static function (string $class): object {
            return match ($class) {
                AsyncNodeHandler::class        => new AsyncNodeHandler(),
                PassthroughNodeHandler::class  => new PassthroughNodeHandler(),
                AsyncFeatureNodeHandler::class => new AsyncFeatureNodeHandler(),
                default => throw new RuntimeException("Unexpected handler: $class"),
            };
        });

        return $container;
    }

    private function makeMachine(): AsyncFeatureMachine
    {
        return new AsyncFeatureMachine($this->makeContainer(), taskBus: $this->makeTaskBus());
    }

    #[Test]
    public function componentSuspendsAfterDispatchUntilStateArrives(): void
    {
        $startName = "state-machine.feature.foundation.component.async.asyncfeaturetes.node1";
        $endName   = "state-machine.feature.foundation.component.async.asyncfeaturetes.node2";
        $startId = NodeId::create($startName);
        $endId   = NodeId::create($endName);

        $component = AsyncComponent::create(
            'payment_result',
            fn(States $s): Task => new AsyncFeatureTask(),
        );
        $component->input->trigger->attach($startId);
        $component->output->done->attach($endId);

        $machine = $this->makeMachine();
        $machine->addNodePublic(new AsyncFeatureNode($startName));
        $machine->addNodePublic(new AsyncFeatureNode($endName));
        $machine->addDefinition($component);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);

        $machine->execute($execution);
        $status = $machine->execute($execution);
        $this->assertSame(ExecutionStatus::Suspended, $status);
    }

    #[Test]
    public function componentCompletesAfterStateIsSet(): void
    {
        $startName = "state-machine.feature.foundation.component.async.asyncfeaturetes.node3";
        $endName   = "state-machine.feature.foundation.component.async.asyncfeaturetes.node4";
        $startId = NodeId::create($startName);
        $endId   = NodeId::create($endName);

        $component = AsyncComponent::create(
            'payment_result',
            fn(States $s): Task => new AsyncFeatureTask(),
        );
        $component->input->trigger->attach($startId);
        $component->output->done->attach($endId);

        $machine = $this->makeMachine();
        $machine->addNodePublic(new AsyncFeatureNode($startName));
        $machine->addNodePublic(new AsyncFeatureNode($endName));
        $machine->addDefinition($component);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);

        $machine->execute($execution);

        $execution->states->defineState('payment_result', [
            new StateDetail('status', 'completed'),
        ]);

        $status = $machine->execute($execution);
        $this->assertSame(ExecutionStatus::Completed, $status);
    }

    #[Test]
    public function dispatchedTaskEnvelopeContainsAwaitStateStamp(): void
    {
        $startName = "state-machine.feature.foundation.component.async.asyncfeaturetes.node5";
        $endName   = "state-machine.feature.foundation.component.async.asyncfeaturetes.node6";
        $startId = NodeId::create($startName);
        $endId   = NodeId::create($endName);

        $component = AsyncComponent::create(
            'payment_result',
            fn(States $s): Task => new AsyncFeatureTask(),
        );
        $component->input->trigger->attach($startId);
        $component->output->done->attach($endId);

        $machine = $this->makeMachine();
        $machine->addNodePublic(new AsyncFeatureNode($startName));
        $machine->addNodePublic(new AsyncFeatureNode($endName));
        $machine->addDefinition($component);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);
        $machine->execute($execution);

        $this->assertNotNull($this->lastEnvelope);

        $awaitStamp = null;
        foreach ($this->lastEnvelope->stamps as $stamp) {
            if ($stamp instanceof AwaitStateStamp) {
                $awaitStamp = $stamp;
                break;
            }
        }

        $this->assertNotNull($awaitStamp);
        $this->assertSame('payment_result', $awaitStamp->stateName);
    }

    #[Test]
    public function taskFactoryReceivesStates(): void
    {
        $startName = "state-machine.feature.foundation.component.async.asyncfeaturetes.node7";
        $endName   = "state-machine.feature.foundation.component.async.asyncfeaturetes.node8";
        $startId = NodeId::create($startName);
        $endId   = NodeId::create($endName);

        $receivedStates = null;
        $component = AsyncComponent::create(
            'result',
            function (States $s) use (&$receivedStates): Task {
                $receivedStates = $s;
                return new AsyncFeatureTask();
            },
        );
        $component->input->trigger->attach($startId);
        $component->output->done->attach($endId);

        $machine = $this->makeMachine();
        $machine->addNodePublic(new AsyncFeatureNode($startName));
        $machine->addNodePublic(new AsyncFeatureNode($endName));
        $machine->addDefinition($component);

        $execution = Execution::create();
        $execution->states->defineState('result', [
            new StateDetail('status', 'completed'),
        ]);
        $execution->pointers->startAt($startId);
        $machine->execute($execution);

        $this->assertInstanceOf(States::class, $receivedStates);
    }
}

class AsyncFeatureMachine extends StateMachine
{
    public function addNodePublic(\PhpArchitecture\StateMachine\Foundation\Node\NodeInterface $node): static
    {
        return $this->addNode($node);
    }
}

class AsyncFeatureNode extends Node
{
    public function handlerClass(): string
    {
        return AsyncFeatureNodeHandler::class;
    }
}

class AsyncFeatureNodeHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        return NodeHandlerResult::Continue;
    }
}

class AsyncFeatureTask implements Task {}

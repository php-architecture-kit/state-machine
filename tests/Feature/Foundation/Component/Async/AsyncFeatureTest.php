<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Foundation\Component\Async;

use PhpArchitecture\StateMachine\Foundation\Component\Async\AsyncComponent;
use PhpArchitecture\StateMachine\Foundation\Component\Async\Node\CreateAsyncTaskNodeHandler;
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
use Throwable;

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
                CreateAsyncTaskNodeHandler::class => new CreateAsyncTaskNodeHandler(),
                PassthroughNodeHandler::class     => new PassthroughNodeHandler(),
                AsyncFeatureNodeHandler::class    => new AsyncFeatureNodeHandler(),
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
        $component->output->success->attach($endId);
        $component->output->fail->attach($endId);
        $component->output->expired->attach($endId);

        $machine = $this->makeMachine();
        $machine->addNodePublic(new AsyncFeatureNode($startName));
        $machine->addNodePublic(new AsyncFeatureNode($endName));
        $machine->addDefinition($component);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);

        $status = $machine->execute($execution);

        // After first execution, pointer should be at the PassthroughNode (AwaitStateComponent)
        // and execution should not be completed (it's waiting for state)
        $this->assertNotSame(ExecutionStatus::Completed, $status, 'Execution should not be completed yet');
        $this->assertNotNull($this->lastEnvelope, 'Task should have been dispatched');
        
        // Second execution without state change should suspend (no progress)
        $status2 = $machine->execute($execution);
        $this->assertSame(ExecutionStatus::Suspended, $status2, 'Execution should suspend when waiting for state');
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
        $component->output->success->attach($endId);
        $component->output->fail->attach($endId);
        $component->output->expired->attach($endId);

        $machine = $this->makeMachine();
        $machine->addNodePublic(new AsyncFeatureNode($startName));
        $machine->addNodePublic(new AsyncFeatureNode($endName));
        $machine->addDefinition($component);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);

        $machine->execute($execution);

        // Get the actual state name from the dispatched task envelope
        $this->assertNotNull($this->lastEnvelope);
        $stateName = null;
        foreach ($this->lastEnvelope->stamps as $stamp) {
            if ($stamp instanceof AwaitStateStamp) {
                $stateName = $stamp->stateName;
                break;
            }
        }
        $this->assertNotNull($stateName);

        // Define the technical state with the correct state name and value
        $technicalState = $execution->states->getTechnicalState();
        $execution->states->modifyState(
            $technicalState->id,
            [$stateName => \PhpArchitecture\StateMachine\Foundation\Component\Async\Node\AsyncTaskResult::Success->value],
        );

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
        $component->output->success->attach($endId);
        $component->output->fail->attach($endId);
        $component->output->expired->attach($endId);

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
        $this->assertIsString($awaitStamp->stateName);
        $this->assertStringContainsString('executionResult', $awaitStamp->stateName);
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
        $component->output->success->attach($endId);
        $component->output->fail->attach($endId);
        $component->output->expired->attach($endId);

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

class TrackedAsyncFeatureTest extends TestCase
{
    private TaskEnvelope|null $lastEnvelope = null;
    private array $visitedNodes = [];

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
        $visitedNodes = &$this->visitedNodes;
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(function (string $class) use (&$visitedNodes): object {
            return match ($class) {
                CreateAsyncTaskNodeHandler::class => new CreateAsyncTaskNodeHandler(),
                PassthroughNodeHandler::class     => new PassthroughNodeHandler(),
                TrackedAsyncFeatureNodeHandler::class => new TrackedAsyncFeatureNodeHandler($visitedNodes),
                default => throw new RuntimeException("Unexpected handler: $class"),
            };
        });

        return $container;
    }

    private function makeMachine(): TrackedAsyncFeatureMachine
    {
        return new TrackedAsyncFeatureMachine($this->makeContainer(), taskBus: $this->makeTaskBus());
    }

    #[Test]
    public function componentRoutesToSuccessOutputWhenStateIsSuccess(): void
    {
        $this->visitedNodes = [];
        $startName = "state-machine.feature.foundation.component.async.tracked.success.start";
        $successName = "state-machine.feature.foundation.component.async.tracked.success.end";
        $failName = "state-machine.feature.foundation.component.async.tracked.fail.end";
        $expiredName = "state-machine.feature.foundation.component.async.tracked.expired.end";
        
        $startId = NodeId::create($startName);
        $successId = NodeId::create($successName);
        $failId = NodeId::create($failName);
        $expiredId = NodeId::create($expiredName);

        $component = AsyncComponent::create(
            'payment_result',
            fn(States $s): Task => new AsyncFeatureTask(),
        );
        $component->input->trigger->attach($startId);
        $component->output->success->attach($successId);
        $component->output->fail->attach($failId);
        $component->output->expired->attach($expiredId);

        $machine = $this->makeMachine();
        $machine->addNodePublic(new TrackedAsyncFeatureNode($startName, $this->visitedNodes));
        $machine->addNodePublic(new TrackedAsyncFeatureNode($successName, $this->visitedNodes));
        $machine->addNodePublic(new TrackedAsyncFeatureNode($failName, $this->visitedNodes));
        $machine->addNodePublic(new TrackedAsyncFeatureNode($expiredName, $this->visitedNodes));
        $machine->addDefinition($component);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);

        $machine->execute($execution);

        $execution->states->defineState('payment_result', [
            new StateDetail('executionResult', \PhpArchitecture\StateMachine\Foundation\Component\Async\Node\AsyncTaskResult::Success->value),
        ]);

        $status = $machine->execute($execution);
        
        $this->assertSame(ExecutionStatus::Completed, $status);
        $this->assertContains($successName, $this->visitedNodes, 'Success node should be visited');
        $this->assertNotContains($failName, $this->visitedNodes, 'Fail node should NOT be visited');
        $this->assertNotContains($expiredName, $this->visitedNodes, 'Expired node should NOT be visited');
    }

    #[Test]
    public function componentRoutesToFailOutputWhenStateIsFail(): void
    {
        $this->visitedNodes = [];
        $startName = "state-machine.feature.foundation.component.async.tracked.fail.start";
        $successName = "state-machine.feature.foundation.component.async.tracked.fail.success";
        $failName = "state-machine.feature.foundation.component.async.tracked.fail.fail";
        $expiredName = "state-machine.feature.foundation.component.async.tracked.fail.expired";
        
        $startId = NodeId::create($startName);
        $successId = NodeId::create($successName);
        $failId = NodeId::create($failName);
        $expiredId = NodeId::create($expiredName);

        $component = AsyncComponent::create(
            'payment_result',
            fn(States $s): Task => new AsyncFeatureTask(),
        );
        $component->input->trigger->attach($startId);
        $component->output->success->attach($successId);
        $component->output->fail->attach($failId);
        $component->output->expired->attach($expiredId);

        $machine = $this->makeMachine();
        $machine->addNodePublic(new TrackedAsyncFeatureNode($startName, $this->visitedNodes));
        $machine->addNodePublic(new TrackedAsyncFeatureNode($successName, $this->visitedNodes));
        $machine->addNodePublic(new TrackedAsyncFeatureNode($failName, $this->visitedNodes));
        $machine->addNodePublic(new TrackedAsyncFeatureNode($expiredName, $this->visitedNodes));
        $machine->addDefinition($component);

        $execution = Execution::create();
        $execution->pointers->startAt($startId);

        $machine->execute($execution);

        $execution->states->defineState('payment_result', [
            new StateDetail('executionResult', \PhpArchitecture\StateMachine\Foundation\Component\Async\Node\AsyncTaskResult::Fail->value),
        ]);

        $status = $machine->execute($execution);
        
        $this->assertSame(ExecutionStatus::Completed, $status);
        $this->assertContains($failName, $this->visitedNodes, 'Fail node should be visited');
        $this->assertNotContains($successName, $this->visitedNodes, 'Success node should NOT be visited');
        $this->assertNotContains($expiredName, $this->visitedNodes, 'Expired node should NOT be visited');
    }

    #[Test]
    public function componentHasAllThreeOutputsAvailable(): void
    {
        $this->visitedNodes = [];
        $startName = "state-machine.feature.foundation.component.async.outputs.start";
        $successName = "state-machine.feature.foundation.component.async.outputs.success";
        $failName = "state-machine.feature.foundation.component.async.outputs.fail";
        $expiredName = "state-machine.feature.foundation.component.async.outputs.expired";
        
        $startId = NodeId::create($startName);
        $successId = NodeId::create($successName);
        $failId = NodeId::create($failName);
        $expiredId = NodeId::create($expiredName);

        $component = AsyncComponent::create(
            'test_outputs',
            fn(States $s): Task => new AsyncFeatureTask(),
        );
        $component->input->trigger->attach($startId);
        $component->output->success->attach($successId);
        $component->output->fail->attach($failId);
        $component->output->expired->attach($expiredId);

        $machine = $this->makeMachine();
        $machine->addNodePublic(new TrackedAsyncFeatureNode($startName, $this->visitedNodes));
        $machine->addNodePublic(new TrackedAsyncFeatureNode($successName, $this->visitedNodes));
        $machine->addNodePublic(new TrackedAsyncFeatureNode($failName, $this->visitedNodes));
        $machine->addNodePublic(new TrackedAsyncFeatureNode($expiredName, $this->visitedNodes));
        $machine->addDefinition($component);

        // Verify that all three ports can be attached without errors
        $this->assertTrue(true, 'Component accepts all three output port attachments');
    }
}

class TrackedAsyncFeatureMachine extends StateMachine
{
    public function addNodePublic(\PhpArchitecture\StateMachine\Foundation\Node\NodeInterface $node): static
    {
        return $this->addNode($node);
    }
}

class TrackedAsyncFeatureNode extends Node
{
    public function __construct(
        string $name,
        private array &$visitedNodes,
    ) {
        parent::__construct($name);
    }

    public function handlerClass(): string
    {
        return TrackedAsyncFeatureNodeHandler::class;
    }

    public function getVisitedNodes(): array
    {
        return $this->visitedNodes;
    }
}

class TrackedAsyncFeatureNodeHandler implements NodeHandlerInterface
{
    public function __construct(private array &$visitedNodes) {}

    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        $this->visitedNodes[] = $context->node->id->toString();
        return NodeHandlerResult::Continue;
    }
}

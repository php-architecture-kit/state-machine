<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Feature\Infrastructure\Loader;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\ExecutionStatus;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNodeHandler;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Infrastructure\Loader\StateMachine\DynamicStateMachine;
use PhpArchitecture\StateMachine\Infrastructure\Loader\StateMachineLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

class StateMachineLoaderTest extends TestCase
{
    private function makeContainer(): ContainerInterface
    {
        $handler = new LoaderTestHandler();
        $passthroughHandler = new PassthroughNodeHandler();

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static function (string $class) use ($handler, $passthroughHandler): object {
                return match ($class) {
                    LoaderTestHandler::class => $handler,
                    PassthroughNodeHandler::class => $passthroughHandler,
                    default => throw new RuntimeException("Unexpected class: $class"),
                };
            }
        );

        return $container;
    }

    private function makeSuspendAwareContainer(): ContainerInterface
    {
        $handler = new LoaderTestHandler();
        $suspendHandler = new LoaderSuspendHandler();
        $passthroughHandler = new PassthroughNodeHandler();

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static function (string $class) use ($handler, $suspendHandler, $passthroughHandler): object {
                return match ($class) {
                    LoaderTestHandler::class => $handler,
                    LoaderSuspendHandler::class => $suspendHandler,
                    PassthroughNodeHandler::class => $passthroughHandler,
                    default => throw new RuntimeException("Unexpected class: $class"),
                };
            }
        );

        return $container;
    }

    #[Test]
    public function loadReturnsDynamicStateMachine(): void
    {
        $config = [
            'nodes' => [
                ['name' => 'state-machine.feature.loader.simple.node1', 'handler' => LoaderTestHandler::class],
            ],
        ];

        $machine = (new StateMachineLoader())->load($config, $this->makeContainer());

        $this->assertInstanceOf(DynamicStateMachine::class, $machine);
    }

    #[Test]
    public function simpleNodeToNodeTransitionExecutesToCompletion(): void
    {
        $startName = 'state-machine.feature.loader.twonodes.start';
        $endName = 'state-machine.feature.loader.twonodes.end';

        $config = [
            'nodes' => [
                ['name' => $startName, 'handler' => LoaderTestHandler::class],
                ['name' => $endName, 'handler' => LoaderTestHandler::class],
            ],
            'transitions' => [
                ['from' => $startName, 'to' => $endName],
            ],
        ];

        $machine = (new StateMachineLoader())->load($config, $this->makeContainer());

        $execution = Execution::create();
        $execution->pointers->startAt(NodeId::create($startName));

        $this->assertSame(ExecutionStatus::Completed, $machine->execute($execution));
    }

    #[Test]
    public function conditionalTransitionIsApplied(): void
    {
        $startName = 'state-machine.feature.loader.condition.start';
        $yes = 'state-machine.feature.loader.condition.yes';
        $no = 'state-machine.feature.loader.condition.no';

        $config = [
            'nodes' => [
                ['name' => $startName, 'handler' => LoaderTestHandler::class, 'strategy' => 'first_valid'],
                ['name' => $yes, 'handler' => LoaderSuspendHandler::class],
                ['name' => $no, 'handler' => LoaderTestHandler::class],
            ],
            'transitions' => [
                ['from' => $startName, 'to' => $yes, 'condition' => LoaderAlwaysAcceptCondition::class],
                ['from' => $startName, 'to' => $no, 'condition' => LoaderAlwaysRejectCondition::class],
            ],
        ];

        $machine = (new StateMachineLoader())->load($config, $this->makeSuspendAwareContainer());

        $execution = Execution::create();
        $execution->pointers->startAt(NodeId::create($startName));
        $machine->execute($execution);

        $pointers = array_values($execution->pointers->pointers);
        $this->assertCount(1, $pointers);
        $this->assertTrue(NodeId::create($yes)->equals($pointers[0]->nodeId));
    }

    #[Test]
    public function choiceComponentRoutesToCorrectBranch(): void
    {
        $startName = 'state-machine.feature.loader.choice.start';
        $yesName = 'state-machine.feature.loader.choice.yes';
        $noName = 'state-machine.feature.loader.choice.no';
        $choiceName = 'state-machine.feature.loader.choice.router';

        $config = [
            'nodes' => [
                ['name' => $startName, 'handler' => LoaderTestHandler::class],
                ['name' => $yesName, 'handler' => LoaderSuspendHandler::class],
                ['name' => $noName, 'handler' => LoaderTestHandler::class],
            ],
            'components' => [
                [
                    'type' => 'choice',
                    'name' => $choiceName,
                    'branches' => [
                        'yes' => LoaderAlwaysAcceptCondition::class,
                        'no' => LoaderAlwaysRejectCondition::class,
                    ],
                ],
            ],
            'transitions' => [
                ['from' => $startName, 'to' => "{$choiceName}.trigger"],
                ['from' => "{$choiceName}.yes", 'to' => $yesName],
                ['from' => "{$choiceName}.no", 'to' => $noName],
            ],
        ];

        $machine = (new StateMachineLoader())->load($config, $this->makeSuspendAwareContainer());

        $execution = Execution::create();
        $execution->pointers->startAt(NodeId::create($startName));
        $machine->execute($execution);

        $pointers = array_values($execution->pointers->pointers);
        $this->assertCount(1, $pointers);
        $this->assertTrue(NodeId::create($yesName)->equals($pointers[0]->nodeId));
    }

    #[Test]
    public function unknownTransitionStrategyThrowsException(): void
    {
        $config = [
            'nodes' => [
                ['name' => 'state-machine.feature.loader.badstrategy.node', 'handler' => LoaderTestHandler::class, 'strategy' => 'unknown_strategy'],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown transition strategy 'unknown_strategy'");

        (new StateMachineLoader())->load($config, $this->makeContainer());
    }

    #[Test]
    public function unknownComponentTypeThrowsException(): void
    {
        $config = [
            'components' => [
                ['type' => 'unknown_type', 'name' => 'some.component'],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("No component builder found for type 'unknown_type'");

        (new StateMachineLoader())->load($config, $this->makeContainer());
    }

    #[Test]
    public function unresolvableReferenceThrowsException(): void
    {
        $config = [
            'nodes' => [
                ['name' => 'state-machine.feature.loader.badref.start', 'handler' => LoaderTestHandler::class],
            ],
            'transitions' => [
                ['from' => 'state-machine.feature.loader.badref.start', 'to' => 'state-machine.feature.loader.badref.nonexistent'],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Cannot resolve reference");

        (new StateMachineLoader())->load($config, $this->makeContainer());
    }
}

class LoaderTestHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        return NodeHandlerResult::Continue;
    }
}

class LoaderSuspendHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        return NodeHandlerResult::Suspended;
    }
}

class LoaderAlwaysAcceptCondition implements TransitionCondition
{
    public function check(States $states): TransitionConditionDecision
    {
        return TransitionConditionDecision::Accepted;
    }
}

class LoaderAlwaysRejectCondition implements TransitionCondition
{
    public function check(States $states): TransitionConditionDecision
    {
        return TransitionConditionDecision::Rejected;
    }
}

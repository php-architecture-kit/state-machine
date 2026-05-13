<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation;

use PhpArchitecture\StateMachine\Foundation\Config\Exception\NoTransitionStrategyException;
use PhpArchitecture\StateMachine\Foundation\Exception\Modification\CannotAddNodeDuringExecutionException;
use PhpArchitecture\StateMachine\Foundation\Exception\Modification\CannotAddTransitionDuringExecutionException;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\ExecutionStatus;
use PhpArchitecture\StateMachine\Foundation\Node\Exception\InvalidNodeHandlerException;
use PhpArchitecture\StateMachine\Foundation\Node\Exception\NodeNotFoundException;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use Psr\Container\ContainerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class StateMachineTest extends TestCase
{
    private function makeMachine(ContainerInterface $container): ConcreteStateMachine
    {
        return new ConcreteStateMachine($container);
    }

    private function makeNode(string $name, string $handlerClass): ConcreteNode
    {
        return new ConcreteNode($name, $handlerClass);
    }

    private function makeContainerWithHandler(string $handlerClass, NodeHandlerInterface $handler): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with($handlerClass)->willReturn($handler);
        return $container;
    }

    #[Test]
    public function executeReturnsCompletedWhenExecutionHasNoPointers(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $machine = $this->makeMachine($container);
        $execution = Execution::create();

        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Completed, $status);
    }

    #[Test]
    public function executeReturnsSuspendedWhenHandlerSuspendsAndNoProgressMade(): void
    {
        $handler = new ContinuousOrSuspendedHandler(NodeHandlerResult::Suspended);
        $container = $this->makeContainerWithHandler(ContinuousOrSuspendedHandler::class, $handler);

        $machine = $this->makeMachine($container);
        $node = $this->makeNode("state-machine.test.node", ContinuousOrSuspendedHandler::class);
        $machine->addNodePublic($node);

        $execution = Execution::create();
        $execution->pointers->startAt($node->id());

        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Suspended, $status);
    }

    #[Test]
    public function executeReturnsRunningWhenPointerMakesProgressButIsNotCompleted(): void
    {
        $continueHandler = new ContinuousOrSuspendedHandler(NodeHandlerResult::Continue);
        $suspendHandler = new ContinuousOrSuspendedHandler(NodeHandlerResult::Suspended);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static function (string $class) use ($continueHandler, $suspendHandler): NodeHandlerInterface {
                return match ($class) {
                    ContinuousOrSuspendedHandler::class . '_continue' => $continueHandler,
                    ContinuousOrSuspendedHandler::class . '_suspend'  => $suspendHandler,
                    default => throw new RuntimeException("Unexpected: $class"),
                };
            },
        );

        $machine = $this->makeMachine($container);
        $nodeA = new ConcreteNode('state-machine.test.node-a', ContinuousOrSuspendedHandler::class . '_continue');
        $nodeB = new ConcreteNode('state-machine.test.node-b', ContinuousOrSuspendedHandler::class . '_suspend');
        $machine->addNodePublic($nodeA);
        $machine->addNodePublic($nodeB);
        $machine->addTransition($nodeA->id(), $nodeB->id());

        $execution = Execution::create();
        $execution->pointers->startAt($nodeA->id());

        $status = $machine->execute($execution, 1);

        $this->assertSame(ExecutionStatus::Running, $status);
    }

    #[Test]
    public function addTransitionAddsDirectedEdgeBetweenNodes(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $machine = $this->makeMachine($container);
        $nodeA = $this->makeNode('state-machine.test.node-a', 'handler');
        $nodeB = $this->makeNode('state-machine.test.node-b', 'handler');
        $machine->addNodePublic($nodeA);
        $machine->addNodePublic($nodeB);

        $machine->addTransition($nodeA->id(), $nodeB->id());

        $edges = $machine->getOutgoingTransitionsPublic($nodeA->id());
        $this->assertCount(1, $edges);
        $this->assertTrue($nodeB->id()->equals($edges[0]->output));
    }

    #[Test]
    public function addTransitionReturnsSameInstance(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $machine = $this->makeMachine($container);
        $nodeA = $this->makeNode('state-machine.test.node-a', 'handler');
        $nodeB = $this->makeNode('state-machine.test.node-b', 'handler');
        $machine->addNodePublic($nodeA);
        $machine->addNodePublic($nodeB);

        $result = $machine->addTransition($nodeA->id(), $nodeB->id());

        $this->assertSame($machine, $result);
    }

    #[Test]
    public function addTransitionWithConditionStoresConditionOnEdge(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $machine = $this->makeMachine($container);
        $nodeA = $this->makeNode('state-machine.test.node-a', 'handler');
        $nodeB = $this->makeNode('state-machine.test.node-b', 'handler');
        $machine->addNodePublic($nodeA);
        $machine->addNodePublic($nodeB);
        $condition = new class implements TransitionCondition {
            public function check(States $states): TransitionConditionDecision
            {
                return TransitionConditionDecision::Accepted;
            }
        };

        $machine->addTransition($nodeA->id(), $nodeB->id(), $condition);

        $edges = $machine->getOutgoingTransitionsPublic($nodeA->id());
        $this->assertSame($condition, $edges[0]->condition);
    }

    #[Test]
    public function getNodeThrowsNodeNotFoundExceptionForUnknownNodeId(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $machine = $this->makeMachine($container);

        $this->expectException(NodeNotFoundException::class);

        $machine->getNodePublic(NodeId::create('state-machine.unknown.node'));
    }

    #[Test]
    public function getOutgoingTransitionsReturnsOnlyEdgesFromGivenNode(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $machine = $this->makeMachine($container);
        $nodeA = $this->makeNode('state-machine.test.node-a', 'h');
        $nodeB = $this->makeNode('state-machine.test.node-b', 'h');
        $nodeC = $this->makeNode('state-machine.test.node-c', 'h');
        $machine->addNodePublic($nodeA);
        $machine->addNodePublic($nodeB);
        $machine->addNodePublic($nodeC);
        $machine->addTransition($nodeA->id(), $nodeB->id());
        $machine->addTransition($nodeA->id(), $nodeC->id());
        $machine->addTransition($nodeB->id(), $nodeC->id());

        $outgoing = $machine->getOutgoingTransitionsPublic($nodeA->id());

        $this->assertCount(2, $outgoing);
        foreach ($outgoing as $edge) {
            $this->assertTrue($nodeA->id()->equals($edge->u()));
        }
    }

    #[Test]
    public function executeThrowsInvalidNodeHandlerExceptionWhenContainerReturnsNonHandler(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn(new stdClass());

        $machine = $this->makeMachine($container);
        $node = $this->makeNode('state-machine.test.node', stdClass::class);
        $machine->addNodePublic($node);

        $execution = Execution::create();
        $execution->pointers->startAt($node->id());

        $this->expectException(InvalidNodeHandlerException::class);

        $machine->execute($execution);
    }

    #[Test]
    public function executeReturnsCompletedWhenNodeHasNoOutgoingTransitions(): void
    {
        $handler = new ContinuousOrSuspendedHandler(NodeHandlerResult::Continue);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($handler);

        $machine = $this->makeMachine($container);
        $node = $this->makeNode('state-machine.test.node', ContinuousOrSuspendedHandler::class);
        $machine->addNodePublic($node);

        $execution = Execution::create();
        $execution->pointers->startAt($node->id());

        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Completed, $status);
    }

    #[Test]
    public function handlerIsInvokedAgainOnSubsequentExecuteAfterSuspended(): void
    {
        $handlerA = new CountingHandler(NodeHandlerResult::Suspended);
        $handlerBC = new ContinuousOrSuspendedHandler(NodeHandlerResult::Suspended);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static function (string $class) use ($handlerA, $handlerBC): NodeHandlerInterface {
                return match ($class) {
                    CountingHandler::class => $handlerA,
                    ContinuousOrSuspendedHandler::class => $handlerBC,
                    default => throw new RuntimeException("Unexpected: $class"),
                };
            },
        );

        $machine = $this->makeMachine($container);
        $nodeA = $this->makeNode('state-machine.test.node-a', CountingHandler::class);
        $nodeB = $this->makeNode('state-machine.test.node-b', ContinuousOrSuspendedHandler::class);
        $nodeC = $this->makeNode('state-machine.test.node-c', ContinuousOrSuspendedHandler::class);
        $machine->addNodePublic($nodeA);
        $machine->addNodePublic($nodeB);
        $machine->addNodePublic($nodeC);
        $machine->addTransition($nodeA->id(), $nodeB->id());
        $machine->addTransition($nodeB->id(), $nodeC->id());

        $execution = Execution::create();
        $execution->pointers->startAt($nodeA->id());

        $machine->execute($execution);
        $machine->execute($execution);

        $this->assertSame(2, $handlerA->callCount);
    }

    #[Test]
    public function suspendedPointerStaysOnSameNodeOnSubsequentExecute(): void
    {
        $suspendHandler = new ContinuousOrSuspendedHandler(NodeHandlerResult::Suspended);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($suspendHandler);

        $machine = $this->makeMachine($container);
        $nodeA = $this->makeNode('state-machine.test.node-a', ContinuousOrSuspendedHandler::class);
        $nodeB = $this->makeNode('state-machine.test.node-b', ContinuousOrSuspendedHandler::class);
        $nodeC = $this->makeNode('state-machine.test.node-c', ContinuousOrSuspendedHandler::class);
        $machine->addNodePublic($nodeA);
        $machine->addNodePublic($nodeB);
        $machine->addNodePublic($nodeC);
        $machine->addTransition($nodeA->id(), $nodeB->id());
        $machine->addTransition($nodeB->id(), $nodeC->id());

        $execution = Execution::create();
        $pointer = $execution->pointers->startAt($nodeA->id());

        $machine->execute($execution);
        $machine->execute($execution);

        $this->assertTrue($nodeA->id()->equals($pointer->nodeId));
    }

    #[Test]
    public function secondExecuteAfterSuspendedReturnsSuspendedWhenHandlerStillSuspends(): void
    {
        $suspendHandler = new ContinuousOrSuspendedHandler(NodeHandlerResult::Suspended);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($suspendHandler);

        $machine = $this->makeMachine($container);
        $nodeA = $this->makeNode('state-machine.test.node-a', ContinuousOrSuspendedHandler::class);
        $nodeB = $this->makeNode('state-machine.test.node-b', ContinuousOrSuspendedHandler::class);
        $nodeC = $this->makeNode('state-machine.test.node-c', ContinuousOrSuspendedHandler::class);
        $machine->addNodePublic($nodeA);
        $machine->addNodePublic($nodeB);
        $machine->addNodePublic($nodeC);
        $machine->addTransition($nodeA->id(), $nodeB->id());
        $machine->addTransition($nodeB->id(), $nodeC->id());

        $execution = Execution::create();
        $execution->pointers->startAt($nodeA->id());

        $machine->execute($execution);
        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Suspended, $status);
    }

    #[Test]
    public function addNodeThrowsCannotAddNodeDuringExecutionExceptionWhenExecutionIsRunning(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $machine = $this->makeMachine($container);
        $nodeA = $this->makeNode('state-machine.test.node-a', AddNodeDuringExecutionHandler::class);
        $nodeToAdd = new ConcreteNode('state-machine.test.node-to-add', ContinuousOrSuspendedHandler::class);
        $machine->addNodePublic($nodeA);

        $handler = new AddNodeDuringExecutionHandler($machine, $nodeToAdd);
        $container->method('get')->with(AddNodeDuringExecutionHandler::class)->willReturn($handler);

        $execution = Execution::create();
        $execution->pointers->startAt($nodeA->id());

        $this->expectException(CannotAddNodeDuringExecutionException::class);
        $machine->execute($execution);
    }

    #[Test]
    public function addTransitionThrowsCannotAddTransitionDuringExecutionExceptionWhenExecutionIsRunning(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $machine = $this->makeMachine($container);
        $nodeA = $this->makeNode('state-machine.test.node-a', AddTransitionDuringExecutionHandler::class);
        $nodeB = $this->makeNode('state-machine.test.node-b', ContinuousOrSuspendedHandler::class);
        $machine->addNodePublic($nodeA);
        $machine->addNodePublic($nodeB);

        $handler = new AddTransitionDuringExecutionHandler($machine, $nodeA->id(), $nodeB->id());
        $container->method('get')->with(AddTransitionDuringExecutionHandler::class)->willReturn($handler);

        $execution = Execution::create();
        $execution->pointers->startAt($nodeA->id());

        $this->expectException(CannotAddTransitionDuringExecutionException::class);
        $machine->execute($execution);
    }

    #[Test]
    public function addNodeAllowedAfterExecutionCompletes(): void
    {
        $handler = new ContinuousOrSuspendedHandler(NodeHandlerResult::Continue);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($handler);

        $machine = $this->makeMachine($container);
        $nodeA = $this->makeNode('state-machine.test.node-a', ContinuousOrSuspendedHandler::class);
        $nodeB = $this->makeNode('state-machine.test.node-b', ContinuousOrSuspendedHandler::class);
        $machine->addNodePublic($nodeA);

        $execution = Execution::create();
        $execution->pointers->startAt($nodeA->id());

        $machine->execute($execution);

        $this->expectNotToPerformAssertions();
        $machine->addNodePublic($nodeB);
    }

    #[Test]
    public function addTransitionAllowedAfterExecutionCompletes(): void
    {
        $handler = new ContinuousOrSuspendedHandler(NodeHandlerResult::Continue);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($handler);

        $machine = $this->makeMachine($container);
        $nodeA = $this->makeNode('state-machine.test.node-a', ContinuousOrSuspendedHandler::class);
        $nodeB = $this->makeNode('state-machine.test.node-b', ContinuousOrSuspendedHandler::class);
        $machine->addNodePublic($nodeA);
        $machine->addNodePublic($nodeB);

        $execution = Execution::create();
        $execution->pointers->startAt($nodeA->id());

        $machine->execute($execution);

        $this->expectNotToPerformAssertions();
        $machine->addTransition($nodeA->id(), $nodeB->id());
    }
}

class ConcreteStateMachine extends StateMachine
{
    public function addNodePublic(\PhpArchitecture\StateMachine\Foundation\Node\NodeInterface $node): static
    {
        return $this->addNode($node);
    }

    public function getNodePublic(NodeId $id): \PhpArchitecture\StateMachine\Foundation\Node\NodeInterface
    {
        return $this->getNode($id);
    }

    /** @return \PhpArchitecture\StateMachine\Foundation\Transition\Transition[] */
    public function getOutgoingTransitionsPublic(NodeId $id): array
    {
        return $this->getOutgoingTransitions($id);
    }
}

class ConcreteNode extends Node
{
    public function __construct(string $name, private readonly string $handler)
    {
        parent::__construct($name);
    }

    public function handlerClass(): string
    {
        return $this->handler;
    }
}

class ContinuousOrSuspendedHandler implements NodeHandlerInterface
{
    public function __construct(private readonly NodeHandlerResult $result) {}

    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        return $this->result;
    }
}

class CountingHandler implements NodeHandlerInterface
{
    public int $callCount = 0;

    public function __construct(private readonly NodeHandlerResult $result) {}

    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        $this->callCount++;

        return $this->result;
    }
}

class AddNodeDuringExecutionHandler implements NodeHandlerInterface
{
    public function __construct(
        private readonly ConcreteStateMachine $machine,
        private readonly ConcreteNode $nodeToAdd,
    ) {}

    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        $this->machine->addNodePublic($this->nodeToAdd);
        return NodeHandlerResult::Continue;
    }
}

class AddTransitionDuringExecutionHandler implements NodeHandlerInterface
{
    public function __construct(
        private readonly ConcreteStateMachine $machine,
        private readonly NodeId $from,
        private readonly NodeId $to,
    ) {}

    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        $this->machine->addTransition($this->from, $this->to);
        return NodeHandlerResult::Continue;
    }
}

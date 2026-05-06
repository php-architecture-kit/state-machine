<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation;

use PhpArchitecture\StateMachine\Foundation\Config\Exception\NoTransitionStrategyException;
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

    private function makeNode(NodeId $id, string $handlerClass): ConcreteNode
    {
        return new ConcreteNode($id, $handlerClass);
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
        $nodeId = NodeId::new();
        $handler = new ContinuousOrSuspendedHandler(NodeHandlerResult::Suspended);
        $container = $this->makeContainerWithHandler(ContinuousOrSuspendedHandler::class, $handler);

        $machine = $this->makeMachine($container);
        $node = $this->makeNode($nodeId, ContinuousOrSuspendedHandler::class);
        $machine->addNodePublic($node);

        $execution = Execution::create();
        $execution->pointers->startAt($nodeId);

        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Suspended, $status);
    }

    #[Test]
    public function executeReturnsRunningWhenPointerMakesProgressButIsNotCompleted(): void
    {
        $nodeIdA = NodeId::new();
        $nodeIdB = NodeId::new();

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
        $nodeA = new ConcreteNode($nodeIdA, ContinuousOrSuspendedHandler::class . '_continue');
        $nodeB = new ConcreteNode($nodeIdB, ContinuousOrSuspendedHandler::class . '_suspend');
        $machine->addNodePublic($nodeA);
        $machine->addNodePublic($nodeB);
        $machine->addTransition($nodeIdA, $nodeIdB);

        $execution = Execution::create();
        $execution->pointers->startAt($nodeIdA);

        $status = $machine->execute($execution);

        $this->assertSame(ExecutionStatus::Running, $status);
    }

    #[Test]
    public function addTransitionAddsDirectedEdgeBetweenNodes(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $machine = $this->makeMachine($container);
        $nodeIdA = NodeId::new();
        $nodeIdB = NodeId::new();
        $machine->addNodePublic($this->makeNode($nodeIdA, 'handler'));
        $machine->addNodePublic($this->makeNode($nodeIdB, 'handler'));

        $machine->addTransition($nodeIdA, $nodeIdB);

        $edges = $machine->getOutgoingTransitionsPublic($nodeIdA);
        $this->assertCount(1, $edges);
        $this->assertTrue($nodeIdB->equals($edges[0]->to));
    }

    #[Test]
    public function addTransitionReturnsSameInstance(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $machine = $this->makeMachine($container);
        $nodeIdA = NodeId::new();
        $nodeIdB = NodeId::new();
        $machine->addNodePublic($this->makeNode($nodeIdA, 'handler'));
        $machine->addNodePublic($this->makeNode($nodeIdB, 'handler'));

        $result = $machine->addTransition($nodeIdA, $nodeIdB);

        $this->assertSame($machine, $result);
    }

    #[Test]
    public function addTransitionWithConditionStoresConditionOnEdge(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $machine = $this->makeMachine($container);
        $nodeIdA = NodeId::new();
        $nodeIdB = NodeId::new();
        $machine->addNodePublic($this->makeNode($nodeIdA, 'handler'));
        $machine->addNodePublic($this->makeNode($nodeIdB, 'handler'));
        $condition = new class implements TransitionCondition {
            public function check(States $states): TransitionConditionDecision
            {
                return TransitionConditionDecision::Accepted;
            }
        };

        $machine->addTransition($nodeIdA, $nodeIdB, $condition);

        $edges = $machine->getOutgoingTransitionsPublic($nodeIdA);
        $this->assertSame($condition, $edges[0]->condition);
    }

    #[Test]
    public function getNodeThrowsNodeNotFoundExceptionForUnknownNodeId(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $machine = $this->makeMachine($container);

        $this->expectException(NodeNotFoundException::class);

        $machine->getNodePublic(NodeId::new());
    }

    #[Test]
    public function getOutgoingTransitionsReturnsOnlyEdgesFromGivenNode(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $machine = $this->makeMachine($container);
        $nodeIdA = NodeId::new();
        $nodeIdB = NodeId::new();
        $nodeIdC = NodeId::new();
        $machine->addNodePublic($this->makeNode($nodeIdA, 'h'));
        $machine->addNodePublic($this->makeNode($nodeIdB, 'h'));
        $machine->addNodePublic($this->makeNode($nodeIdC, 'h'));
        $machine->addTransition($nodeIdA, $nodeIdB);
        $machine->addTransition($nodeIdA, $nodeIdC);
        $machine->addTransition($nodeIdB, $nodeIdC);

        $outgoing = $machine->getOutgoingTransitionsPublic($nodeIdA);

        $this->assertCount(2, $outgoing);
        foreach ($outgoing as $edge) {
            $this->assertTrue($nodeIdA->equals($edge->u()));
        }
    }

    #[Test]
    public function executeThrowsInvalidNodeHandlerExceptionWhenContainerReturnsNonHandler(): void
    {
        $nodeId = NodeId::new();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn(new stdClass());

        $machine = $this->makeMachine($container);
        $machine->addNodePublic($this->makeNode($nodeId, stdClass::class));

        $execution = Execution::create();
        $execution->pointers->startAt($nodeId);

        $this->expectException(InvalidNodeHandlerException::class);

        $machine->execute($execution);
    }

    #[Test]
    public function executeThrowsNoTransitionStrategyExceptionWhenNoStrategyMatchesOutput(): void
    {
        $nodeId = NodeId::new();
        $handler = new ContinuousOrSuspendedHandler(NodeHandlerResult::Continue);
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturn($handler);

        $machine = $this->makeMachine($container);
        $machine->addNodePublic($this->makeNode($nodeId, ContinuousOrSuspendedHandler::class));

        $execution = Execution::create();
        $execution->pointers->startAt($nodeId);

        $this->expectException(NoTransitionStrategyException::class);

        $machine->execute($execution);
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
    public function __construct(NodeId $id, private readonly string $handler)
    {
        parent::__construct($id);
    }

    public function id(): NodeId
    {
        return $this->id;
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

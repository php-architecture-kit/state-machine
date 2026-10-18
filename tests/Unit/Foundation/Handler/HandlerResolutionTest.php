<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Handler;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\ExecutionStatus;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNode;
use PhpArchitecture\StateMachine\Foundation\Node\Variant\Passthrough\PassthroughNodeHandler;
use PhpArchitecture\StateMachine\Foundation\StateMachine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

class HandlerResolutionTest extends TestCase
{
    #[Test]
    public function builtInHandlersWorkWithoutBeingRegistered(): void
    {
        $node = new PassthroughNode('handler-resolution.passthrough');
        $machine = new HandlerResolutionMachine(new EmptyContainer(), $node);
        $execution = Execution::create();
        $execution->pointers->startAt($node->id);

        $this->assertSame(ExecutionStatus::Completed, $machine->execute($execution));
    }

    #[Test]
    public function registeredBuiltInHandlerIsTakenFromTheContainer(): void
    {
        $handler = new PassthroughNodeHandler();
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->expects($this->once())->method('get')->with(PassthroughNodeHandler::class)->willReturn($handler);

        $node = new PassthroughNode('handler-resolution.registered');
        $machine = new HandlerResolutionMachine($container, $node);
        $execution = Execution::create();
        $execution->pointers->startAt($node->id);

        $machine->execute($execution);
    }

    #[Test]
    public function applicationHandlersMustComeFromTheContainer(): void
    {
        $node = new HandlerResolutionApplicationNode('handler-resolution.application');
        $machine = new HandlerResolutionMachine(new EmptyContainer(), $node);
        $execution = Execution::create();
        $execution->pointers->startAt($node->id);

        $this->expectException(NotFoundExceptionInterface::class);
        $machine->execute($execution);
    }
}

final class HandlerResolutionMachine extends StateMachine
{
    public function __construct(ContainerInterface $container, Node $node)
    {
        parent::__construct($container);
        $this->addNode($node);
    }
}

final class HandlerResolutionApplicationNode extends Node
{
    public function handlerClass(): string
    {
        return HandlerResolutionApplicationHandler::class;
    }
}

final class HandlerResolutionApplicationHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        return NodeHandlerResult::Continue;
    }
}

final class EmptyContainer implements ContainerInterface
{
    public function get(string $id): mixed
    {
        throw new class ("No service {$id}") extends RuntimeException implements NotFoundExceptionInterface {};
    }

    public function has(string $id): bool
    {
        return false;
    }
}

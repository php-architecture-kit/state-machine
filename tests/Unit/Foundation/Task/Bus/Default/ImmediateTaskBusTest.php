<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Task\Bus\Default;

use PhpArchitecture\StateMachine\Foundation\Task\Attribute\Defferred;
use PhpArchitecture\StateMachine\Foundation\Task\Attribute\HandledBy;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Default\DeferredTaskBus;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Default\ImmediateTaskBus;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Defferred\MissingDeferredTaskBusException;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Handler\HandlerNotFoundException;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Handler\InvalidHandlerException;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Handler\MissingHandledByAttributeException;
use PhpArchitecture\StateMachine\Foundation\Task\Handler\TaskHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

class ImmediateTaskBusTest extends TestCase
{
    private function makeContainer(array $services = []): ContainerInterface
    {
        return new class($services) implements ContainerInterface {
            public function __construct(private array $services) {}
            
            public function get(string $id): mixed
            {
                return $this->services[$id] ?? throw new RuntimeException("Service {$id} not found");
            }
            
            public function has(string $id): bool
            {
                return isset($this->services[$id]);
            }
        };
    }

    #[Test]
    public function dispatchCallsHandlerImmediately(): void
    {
        $handled = false;
        $handler = new class($handled) implements TaskHandlerInterface {
            public function __construct(private bool &$handled) {}
            public function handle(TaskEnvelope $envelope): void { $this->handled = true; }
        };

        $container = $this->makeContainer([TestHandler::class => $handler]);
        $bus = new ImmediateTaskBus($container);

        $task = new #[HandledBy(TestHandler::class)] class implements Task {};
        $bus->dispatch($task);

        $this->assertTrue($handled);
    }

    #[Test]
    public function returnsEnvelopeAfterHandling(): void
    {
        $handler = new class implements TaskHandlerInterface {
            public function handle(TaskEnvelope $envelope): void {}
        };

        $container = $this->makeContainer([TestHandler::class => $handler]);
        $bus = new ImmediateTaskBus($container);

        $task = new #[HandledBy(TestHandler::class)] class implements Task {};
        $envelope = $bus->dispatch($task);

        $this->assertInstanceOf(TaskEnvelope::class, $envelope);
        $this->assertSame($task, $envelope->task);
    }

    #[Test]
    public function throwsMissingHandledByAttributeWhenNoAttribute(): void
    {
        $container = $this->makeContainer();
        $bus = new ImmediateTaskBus($container);

        $task = new class implements Task {};

        $this->expectException(MissingHandledByAttributeException::class);
        $bus->dispatch($task);
    }

    #[Test]
    public function throwsHandlerNotFoundWhenNotInContainer(): void
    {
        $container = $this->makeContainer();
        $bus = new ImmediateTaskBus($container);

        $task = new #[HandledBy(NonExistentHandler::class)] class implements Task {};

        $this->expectException(HandlerNotFoundException::class);
        $bus->dispatch($task);
    }

    #[Test]
    public function defersToDeferredTaskBusWhenDefferredAttributePresent(): void
    {
        $dummyTask = new class implements Task {};
        $envelope = TaskEnvelope::create($dummyTask);
        
        $deferredBus = $this->createMock(DeferredTaskBus::class);
        $deferredBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $container = $this->makeContainer();
        $bus = new ImmediateTaskBus($container, $deferredBus);

        $task = new #[Defferred] #[HandledBy(TestHandler::class)] class implements Task {};
        $result = $bus->dispatch($task);
        
        $this->assertSame($envelope, $result);
    }

    #[Test]
    public function throwsMissingDeferredTaskBusWhenNoDeferredBusConfigured(): void
    {
        $container = $this->makeContainer();
        $bus = new ImmediateTaskBus($container); // no deferred bus

        $task = new #[Defferred] #[HandledBy(TestHandler::class)] class implements Task {};

        $this->expectException(MissingDeferredTaskBusException::class);
        $bus->dispatch($task);
    }

    #[Test]
    public function handlerReceivesEnvelopeWithStamps(): void
    {
        $container = $this->makeContainer([CapturingHandler::class => new CapturingHandler()]);
        $bus = new ImmediateTaskBus($container);

        $task = new #[HandledBy(CapturingHandler::class)] class implements Task {};
        $bus->dispatch($task);

        $handler = $container->get(CapturingHandler::class);
        $this->assertNotNull($handler->captured);
        $this->assertSame($task, $handler->captured->task);
    }
}

// Test helper classes
class TestHandler implements TaskHandlerInterface
{
    public function handle(TaskEnvelope $envelope): void {}
}

class NonExistentHandler implements TaskHandlerInterface
{
    public function handle(TaskEnvelope $envelope): void {}
}

class CapturingHandler implements TaskHandlerInterface
{
    public ?TaskEnvelope $captured = null;
    public function handle(TaskEnvelope $envelope): void { $this->captured = $envelope; }
}

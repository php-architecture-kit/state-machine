<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit;

use PhpArchitecture\StateMachine\Foundation\Task\Bus\Default\ImmediateTaskBus;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PhpArchitecture\StateMachine\Foundation\Task\Handler\TaskHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class ImmediateTaskBusTest extends TestCase
{
    /** @var MockObject|ContainerInterface */
    private $mockContainer;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup mock container for dependency injection
        $this->mockContainer = $this->createMock(ContainerInterface::class);
    }

    /**
     * @test
     */
    public function it_dispatches_task_immediately_and_passes_execution_context(): void
    {
        // 1. Setup Mocks and Dependencies
        $mockTask = $this->createMock(Task::class);
        $mockHandler = $this->createMock(TaskHandlerInterface::class);
        $mockExecution = $this->createMock(Execution::class); // The optional context object

        // Expectations for the handler: Must be called with (TaskEnvelope, Execution)
        $mockHandler->expects($this->once())
            ->method('handle')
            ->with(
                $this->isInstanceOf(TaskEnvelope::class),
                $this->equalTo($mockExecution), // Check that the second argument is precisely $mockExecution
            );

        // Configure container to return our mock handler
        $this->mockContainer->expects($this->once())
            ->method('get')
            ->with(self::isType(TaskHandlerInterface::class))
            ->willReturn($mockHandler);

        // 2. Initialize the service under test (ImmediateTaskBus)
        /** @var ImmediateTaskBus $bus */
        $bus = new ImmediateTaskBus($this->mockContainer);

        // 3. Execute the method being tested
        $envelope = $bus->dispatch($mockTask, [], $mockExecution);

        // 4. Assertions (basic check)
        $this->assertInstanceOf(TaskEnvelope::class, $envelope);
    }


    /**
     * @test
     */
    public function it_dispatches_task_without_passing_execution_context_if_null(): void
    {
        // This test case verifies the existing behavior for backward compatibility.
        $mockTask = $this->createMock(Task::class);
        $mockHandler = $this->createMock(TaskHandlerInterface::class);

        // Expectations: Handler must be called with (TaskEnvelope, null)
        $mockHandler->expects($this->once())
            ->method('handle')
            ->with(
                $this->isInstanceOf(TaskEnvelope::class),
                $this->isNull(), // Check that the second argument is explicitly null
            );

        // Configure container to return our mock handler
        $this->mockContainer->expects($this->once())
            ->method('get')
            ->with(self::isType(TaskHandlerInterface::class))
            ->willReturn($mockHandler);

        /** @var ImmediateTaskBus $bus */
        $bus = new ImmediateTaskBus($this->mockContainer);

        // Execute the method being tested, passing null for execution context
        $envelope = $bus->dispatch($mockTask, [], null);

        $this->assertInstanceOf(TaskEnvelope::class, $envelope);
    }
}

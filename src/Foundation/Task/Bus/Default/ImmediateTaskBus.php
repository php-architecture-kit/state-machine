<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Bus\Default;

use PhpArchitecture\StateMachine\Foundation\Task\Attribute\Defferred;
use PhpArchitecture\StateMachine\Foundation\Task\Attribute\HandledBy;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskBusInterface;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskStamp;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Defferred\MissingDeferredTaskBusException;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Handler\HandlerNotFoundException;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Handler\InvalidHandlerException;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Handler\MissingHandledByAttributeException;
use PhpArchitecture\StateMachine\Foundation\Task\Handler\TaskHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use Psr\Container\ContainerInterface;
use ReflectionClass;

class ImmediateTaskBus implements TaskBusInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ?DeferredTaskBus $deferredTaskBus = null,
        private readonly ?Execution $execution = null,
    ) {}

    /**
     * @param TaskStamp[] $stamps
     */
    public function dispatch(Task $task, array $stamps = []): TaskEnvelope
    {
        $envelope = TaskEnvelope::create($task, $stamps);

        // Check for Defferred attribute
        if ($this->isDeferred($task)) {
            if ($this->deferredTaskBus === null) {
                throw MissingDeferredTaskBusException::create();
            }
            return $this->deferredTaskBus->dispatch($task, $stamps);
        }

        // Handle immediately
        $handler = $this->resolveHandler($task);
        $handler->handle($envelope, $this->execution);

        return $envelope;
    }

    private function isDeferred(Task $task): bool
    {
        $reflection = new ReflectionClass($task);
        return !empty($reflection->getAttributes(Defferred::class));
    }

    private function resolveHandler(Task $task): TaskHandlerInterface
    {
        $reflection = new ReflectionClass($task);
        $attributes = $reflection->getAttributes(HandledBy::class);

        if (empty($attributes)) {
            throw MissingHandledByAttributeException::create($reflection->getName());
        }

        /** @var HandledBy $handledBy */
        $handledBy = $attributes[0]->newInstance();
        $handlerClass = $handledBy->handlerClass;

        if (!$this->container->has($handlerClass)) {
            throw HandlerNotFoundException::create($handlerClass);
        }

        $handler = $this->container->get($handlerClass);

        if (!$handler instanceof TaskHandlerInterface) {
            throw InvalidHandlerException::create($handlerClass);
        }

        return $handler;
    }
}

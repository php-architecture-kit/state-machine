<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Attribute;

use Attribute;
use PhpArchitecture\StateMachine\Foundation\Task\Handler\TaskHandlerInterface;
use InvalidArgumentException;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class HandledBy
{
    /**
     * @param class-string<TaskHandlerInterface> $handlerClass
     */
    public function __construct(
        public string $handlerClass,
    ) {
        if (!is_subclass_of($this->handlerClass, TaskHandlerInterface::class)) {
            throw new InvalidArgumentException('Handler class must implement TaskHandlerInterface');
        }
    }
}

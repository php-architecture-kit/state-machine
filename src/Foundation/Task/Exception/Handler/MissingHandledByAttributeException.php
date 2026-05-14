<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Exception\Handler;

use RuntimeException;

final class MissingHandledByAttributeException extends RuntimeException implements TaskHandlerException
{
    public static function create(string $taskClass): self
    {
        return new self(
            sprintf('Task %s must have HandledBy attribute.', $taskClass),
        );
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Exception\Handler;

use RuntimeException;

final class HandlerNotFoundException extends RuntimeException implements TaskHandlerException
{
    public static function create(string $handlerClass): self
    {
        return new self(
            sprintf('Handler %s not found in container.', $handlerClass),
        );
    }
}

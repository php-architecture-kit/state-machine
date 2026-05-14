<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Exception\Defferred;

use RuntimeException;

final class MissingDeferredTaskBusException extends RuntimeException implements DeferredTaskBusException
{
    public static function create(): self
    {
        return new self('Task marked as Defferred but no DeferredTaskBus configured.');
    }
}

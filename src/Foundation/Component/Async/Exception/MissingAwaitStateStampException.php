<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Async\Exception;

use RuntimeException;

final class MissingAwaitStateStampException extends RuntimeException implements AsyncTaskException
{
    public static function create(): self
    {
        return new self(
            'TaskEnvelope does not contain AwaitStateStamp. ' .
            'Cannot determine which state key to set.',
        );
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\Async\Exception;

use PhpArchitecture\StateMachine\Foundation\Component\Async\Exception\AsyncTaskException;
use PhpArchitecture\StateMachine\Foundation\Component\Async\Exception\MissingAwaitStateStampException;
use PhpArchitecture\StateMachine\Foundation\Exception\StateMachineException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MissingAwaitStateStampExceptionTest extends TestCase
{
    #[Test]
    public function createReturnsExceptionWithCorrectMessage(): void
    {
        $exception = MissingAwaitStateStampException::create();

        $this->assertSame(
            'TaskEnvelope does not contain AwaitStateStamp. Cannot determine which state key to set.',
            $exception->getMessage(),
        );
    }

    #[Test]
    public function implementsAsyncTaskException(): void
    {
        $exception = MissingAwaitStateStampException::create();

        $this->assertInstanceOf(AsyncTaskException::class, $exception);
    }

    #[Test]
    public function implementsStateMachineException(): void
    {
        $exception = MissingAwaitStateStampException::create();

        $this->assertInstanceOf(StateMachineException::class, $exception);
    }

    #[Test]
    public function extendsRuntimeException(): void
    {
        $exception = MissingAwaitStateStampException::create();

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }
}

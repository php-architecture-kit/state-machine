<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Handler;

use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;

interface TaskHandlerInterface
{
    public function handle(TaskEnvelope $envelope, ?Execution $execution = null): void;
}

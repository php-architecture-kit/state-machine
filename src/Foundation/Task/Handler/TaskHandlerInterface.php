<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Handler;

use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;

interface TaskHandlerInterface
{
    public function handle(TaskEnvelope $envelope): void;
}

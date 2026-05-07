<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Bus;

use PhpArchitecture\StateMachine\Foundation\Task\Task;

interface TaskBusInterface
{
    /**
     * @param TaskStamp[] $stamps
     */
    public function dispatch(Task $task, array $stamps = []): TaskEnvelope;
}

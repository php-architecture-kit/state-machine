<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Bus\Default;

use PhpArchitecture\Clock\SystemClock;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Stamp\DeferredAtStamp;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskBusInterface;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskStamp;
use Psr\Clock\ClockInterface;

class DeferredTaskBus implements TaskBusInterface
{
    /** 
     * @var TaskEnvelope[]
     */
    public private(set) array $defferedTasks = [];

    public function __construct(
        protected readonly ClockInterface $clock = new SystemClock,
    ) {}

    /**
     * @param TaskStamp[] $stamps
     */
    public function dispatch(Task $task, array $stamps = []): TaskEnvelope
    {
        $envelope = TaskEnvelope::create($task, $stamps);
        $envelope->addStamp(DeferredAtStamp::create($this->clock));

        $this->defferedTasks[] = $envelope;

        return $envelope;
    }
}

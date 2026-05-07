<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Bus;

use PhpArchitecture\StateMachine\Foundation\Task\Exception\Dispatch\DuplicateTaskKeyStampException;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Dispatch\InvalidTaskStampException;
use PhpArchitecture\StateMachine\Foundation\Task\Identity\TaskId;
use PhpArchitecture\StateMachine\Foundation\Task\Identity\TaskKey;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PhpArchitecture\Technical\Assert;

final class TaskEnvelope
{
    /**
     * @param TaskStamp[] $stamps
     */
    public function __construct(
        public readonly TaskId $id,
        public readonly Task $task,
        public private(set) ?TaskKey $key,
        public private(set) array $stamps = [],
    ) {
        Assert::eachInstanceOf($stamps, TaskStamp::class, InvalidTaskStampException::class);
    }

    /**
     * @param TaskStamp[] $stamps
     */
    public static function create(
        Task $task,
        array $stamps = [],
    ): self {
        $instance =  new self(
            TaskId::new(),
            $task,
            null,
            $stamps,
        );

        $keyStamps = $instance->getStamps(static fn(TaskStamp $stamp): bool => $stamp instanceof TaskKey);
        if (!empty($keyStamps)) {
            if (($given = count($keyStamps)) > 1) {
                throw new DuplicateTaskKeyStampException("Task can have only one key stamp during creation. {$given} given.");
            }

            $key = $keyStamps[0];
            assert($key instanceof TaskKey);
            $instance->key = $key;
        }

        return $instance;
    }

    public function addStamp(TaskStamp $stamp): self
    {
        if ($stamp instanceof TaskKey && $this->key !== null) {
            throw new DuplicateTaskKeyStampException("Task envelope already has a key stamp '{$this->key}'. Cannot add another.");
        }

        $this->stamps[] = $stamp;

        if ($stamp instanceof TaskKey) {
            $this->key = $stamp;
        }

        return $this;
    }

    /** 
     * @param ?callable(TaskStamp):bool $filter
     * @return TaskStamp[]
     */
    public function getStamps(?callable $filter = null): array
    {
        return $filter ? array_filter($this->stamps, $filter) : $this->stamps;
    }
}

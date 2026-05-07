<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Async\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use Closure;

class AsyncNode extends Node
{
    /**
     * @param string                    $stateName   State key to await after task dispatch. Passed automatically
     *                                               as AwaitStateStamp to the dispatched Task envelope.
     * @param Closure(States): Task    $taskFactory Factory invoked once when the pointer passes this node.
     *                                               Receives current States and must return a Task to dispatch.
     */
    public function __construct(
        public readonly string $stateName,
        public readonly Closure $taskFactory,
    ) {
        parent::__construct();
    }

    public function handlerClass(): string
    {
        return AsyncNodeHandler::class;
    }
}

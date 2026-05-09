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
     * @param string                    $globallyUniqueName Unique name across all state machines
     * @param string                    $stateName   State key to await after task dispatch. Passed automatically
     *                                               as AwaitStateStamp to the dispatched Task envelope.
     * @param Closure(States): Task    $taskFactory Factory invoked once when the pointer passes this node.
     *                                               Receives current States and must return a Task to dispatch.
     * @param string[]                  $tags
     */
    public function __construct(
        string $globallyUniqueName,
        public readonly string $stateName,
        public readonly Closure $taskFactory,
        array $tags = [],
    ) {
        parent::__construct($globallyUniqueName, $tags);
    }

    public function handlerClass(): string
    {
        return AsyncNodeHandler::class;
    }
}

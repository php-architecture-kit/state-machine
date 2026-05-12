<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Async\Node;

use Closure;
use PhpArchitecture\StateMachine\Foundation\Node\Node;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Task\Task;

class CreateAsyncTaskNode extends Node
{
    /**
     * @param Closure(States): Task $taskFactory
     */
    public function __construct(
        string $uniqueName,
        public readonly Closure $taskFactory,
    ) {
        parent::__construct($uniqueName);
    }

    public function handlerClass(): string
    {
        return CreateAsyncTaskNodeHandler::class;
    }

    public function stateName(): string
    {
        return $this->id->toString() . '.executionResult';
    }
}

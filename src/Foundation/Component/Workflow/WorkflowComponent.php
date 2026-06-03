<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Workflow;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;

class WorkflowComponent extends Definition
{
    /**
     * @param Node\WorkflowStep[] $steps
     * @param Transition\WorkflowTransition[] $transitions
     */
    public static function create(
        string $uniqueName,
        array $steps,
        array $transitions,
    ): self {
        $name = "state-machine.workflow.{$uniqueName}";
        $instance = parent::newInstance(
            $name,
            ['init'],
            ['end'],
        );

        // TODO: build

        return $instance;
    }
}

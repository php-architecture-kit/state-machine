<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Workflow\Node;

use Closure;

class WorkflowStep
{
    public function __construct(
        public readonly string $name,
        public readonly ?Closure $taskFactory = null,
    ) {
    }
}

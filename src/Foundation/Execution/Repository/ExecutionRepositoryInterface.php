<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Execution\Repository;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;

interface ExecutionRepositoryInterface
{
    public function save(Execution $execution): void;

    public function find(ExecutionId $id): ?Execution;
}

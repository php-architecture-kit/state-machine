<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Serialization;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;

interface ExecutionNormalizerInterface
{
    /** @return array<string,mixed> */
    public function normalize(Execution $execution): array;

    /** @param array<string,mixed> $data */
    public function denormalize(array $data): Execution;
}

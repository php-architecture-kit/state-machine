<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Bus\Stamp;

use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskStamp;

final readonly class AwaitStateStamp implements TaskStamp
{
    public function __construct(
        public string $stateName,
    ) {}
}

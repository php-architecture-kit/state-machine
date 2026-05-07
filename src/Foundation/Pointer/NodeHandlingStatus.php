<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer;

enum NodeHandlingStatus: string
{
    case Completed = 'completed';
    case Pending = 'pending';
}

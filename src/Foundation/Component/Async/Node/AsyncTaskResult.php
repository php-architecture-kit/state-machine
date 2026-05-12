<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Async\Node;

enum AsyncTaskResult: string
{
    case Fail = 'fail';
    case Success = 'success';
}

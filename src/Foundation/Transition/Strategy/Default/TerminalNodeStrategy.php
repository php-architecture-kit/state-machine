<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Output\TransitionSelectionOutput;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\TransitionStrategy;

final class TerminalNodeStrategy implements TransitionStrategy
{
    public function supports(TransitionSelectionOutput $transitionSelection): bool
    {
        return empty($transitionSelection->goto)
            && empty($transitionSelection->waitfor)
            && empty($transitionSelection->reject);
    }

    public function transitionToNextNodes(
        Execution $execution,
        Pointer $pointer,
        TransitionSelectionOutput $transitionSelection,
    ): void {
        $execution->pointers->remove($pointer->id);
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Exception\Modification;

use LogicException;

final class CannotAddTransitionDuringExecutionException extends LogicException implements StateMachineModificationException {}

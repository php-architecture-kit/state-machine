<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Exception\Modification;

use LogicException;

final class CannotAddNodeDuringExecutionException extends LogicException implements StateMachineModificationException {}

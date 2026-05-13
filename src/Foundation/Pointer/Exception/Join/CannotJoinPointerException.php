<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer\Exception\Join;

use RuntimeException;

final class CannotJoinPointerException extends RuntimeException implements PointerJoinException {}

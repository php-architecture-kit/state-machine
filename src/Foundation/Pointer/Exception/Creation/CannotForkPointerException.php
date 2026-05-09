<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Pointer\Exception\Creation;

use RuntimeException;

final class CannotForkPointerException extends RuntimeException implements PointerCreationException {}

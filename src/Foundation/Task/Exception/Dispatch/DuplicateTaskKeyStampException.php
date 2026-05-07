<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Exception\Dispatch;

use InvalidArgumentException;

final class DuplicateTaskKeyStampException extends InvalidArgumentException implements TaskDispatchException {}

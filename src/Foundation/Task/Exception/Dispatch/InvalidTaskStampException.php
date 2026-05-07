<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Exception\Dispatch;

use InvalidArgumentException;

final class InvalidTaskStampException extends InvalidArgumentException implements TaskDispatchException {}

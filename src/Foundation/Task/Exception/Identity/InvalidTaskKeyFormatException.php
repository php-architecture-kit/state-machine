<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Exception\Identity;

use InvalidArgumentException;

final class InvalidTaskKeyFormatException extends InvalidArgumentException implements TaskIdentityException {}

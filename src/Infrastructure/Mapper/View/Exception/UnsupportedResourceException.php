<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Exception;

use RuntimeException;

final class UnsupportedResourceException extends RuntimeException implements StateMachineViewMappingException {}

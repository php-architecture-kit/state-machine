<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Definition\Exception;

use LogicException;

final class CircularPortAttachmentException extends LogicException implements DefinitionException
{
    public static function involving(string $portId): self
    {
        return new self(sprintf(
            'Circular port attachment detected involving port "%s". '
            . 'A port chain must terminate at either null or a non-port node. '
            . 'Hint: review the Port::attach() calls in this definition - one of '
            . 'the attached ports forms a cycle back to itself.',
            $portId,
        ));
    }
}

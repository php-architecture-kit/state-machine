<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Definition\Exception;

use LogicException;

final class DanglingPortAttachmentException extends LogicException implements DefinitionException
{
    public static function for(string $portId, string $missingPortId): self
    {
        return new self(sprintf(
            'Port "%s" is attached to port "%s" which is not part of the definition. '
            . 'Hint: either add the missing port to the definition before compiling, '
            . 'or attach the port directly to a node that is part of it.',
            $portId,
            $missingPortId,
        ));
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Definition\Exception;

use LogicException;

final class DuplicatePortNameException extends LogicException implements DefinitionException
{
    /**
     * @param string[] $names
     */
    public static function forInputs(array $names): self
    {
        return new self(sprintf(
            'Cannot merge definitions: duplicate input port names found: %s. '
            . 'Hint: either rename the conflicting ports in one of the definitions, '
            . 'or connect them via the inputPortMapping argument to merge().',
            implode(', ', $names),
        ));
    }

    /**
     * @param string[] $names
     */
    public static function forOutputs(array $names): self
    {
        return new self(sprintf(
            'Cannot merge definitions: duplicate output port names found: %s. '
            . 'Hint: either rename the conflicting ports in one of the definitions, '
            . 'or connect them via the outputPortMapping argument to merge().',
            implode(', ', $names),
        ));
    }
}

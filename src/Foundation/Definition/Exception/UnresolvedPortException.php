<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Definition\Exception;

use LogicException;

final class UnresolvedPortException extends LogicException implements DefinitionException
{
    /**
     * @param string[] $portIds
     */
    public static function forPorts(array $portIds): self
    {
        return new self(sprintf(
            'Compiled definition still contains %d unresolved port(s): [%s]. '
            . 'This indicates a compiler bug: every port should have been replaced '
            . 'by its terminal node or removed during compilation.',
            count($portIds),
            implode(', ', array_map(static fn(string $id): string => '"' . $id . '"', $portIds)),
        ));
    }
}

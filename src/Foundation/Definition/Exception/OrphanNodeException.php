<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Definition\Exception;

use LogicException;

final class OrphanNodeException extends LogicException implements DefinitionException
{
    /**
     * @param string[] $nodeIds
     */
    public static function forNodes(array $nodeIds): self
    {
        return new self(sprintf(
            'Compiled definition contains %d orphan node(s) without any transition: [%s]. '
            . 'Every node in a compiled definition must participate in at least one '
            . 'transition. Hint: either connect each node via addTransition(), '
            . 'attach it to an input/output port, or remove it from the definition.',
            count($nodeIds),
            implode(', ', array_map(static fn(string $id): string => '"' . $id . '"', $nodeIds)),
        ));
    }
}

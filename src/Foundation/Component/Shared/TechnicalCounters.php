<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Shared;

use PhpArchitecture\StateMachine\Foundation\State\States;

/**
 * Integer counters kept in the technical state, persisted together with the Execution.
 * A counter at zero is removed, so a component that finished its round leaves nothing behind.
 *
 * @internal
 */
final class TechnicalCounters
{
    public static function get(States $states, string $name): int
    {
        $value = $states->getTechnicalState()[$name]?->value;

        return is_int($value) ? $value : 0;
    }

    /**
     * @param array<string,int> $changes counter name => delta
     */
    public static function change(States $states, array $changes): void
    {
        $set = [];
        $remove = [];
        foreach ($changes as $name => $delta) {
            $value = self::get($states, $name) + $delta;
            if ($value > 0) {
                $set[$name] = $value;
            } else {
                $remove[] = $name;
            }
        }

        $states->modifyState($states->getTechnicalState()->id, $set, $remove);
    }
}

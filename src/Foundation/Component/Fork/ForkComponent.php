<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Fork;

use PhpArchitecture\StateMachine\Foundation\Component\Fork\Node\ForkNode;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;

class ForkComponent extends Definition
{
    /**
     * Creates a fork (AND-split) component that spawns one pointer per matching output branch.
     *
     * By default all branches fire unconditionally. Pass optional per-branch predicates via
     * $conditions to make specific branches conditional — branches without a predicate always fire.
     *
     * @param string[]                              $branches   Names of the parallel output branches.
     * @param array<string, callable(States): bool> $conditions Optional map of branch-name => predicate.
     */
    public static function create(array $branches, array $conditions = []): self
    {
        $forkNode = new ForkNode();

        $instance = self::newInstance(
            $forkNode->name(),
            inputs: ['trigger'],
            outputs: $branches,
        );

        $instance->addTransition(
            $instance->input->trigger, // @phpstan-ignore-line
            $forkNode,
            null,
        );

        foreach ($branches as $branch) {
            $predicate = $conditions[$branch] ?? null;

            $instance->addTransition(
                $forkNode,
                $instance->output->{$branch}, // @phpstan-ignore-line
                $predicate === null
                    ? null
                    : static function (States $states) use ($predicate): TransitionConditionDecision {
                        return $predicate($states)
                            ? TransitionConditionDecision::Accepted
                            : TransitionConditionDecision::Rejected;
                    },
            );
        }

        return $instance;
    }
}

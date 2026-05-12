<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Fork;

use LogicException;
use PhpArchitecture\StateMachine\Foundation\Component\Fork\Node\ForkNode;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\AllValidTransitionsStrategy;
use PhpArchitecture\Technical\Assert;

class ForkComponent extends Definition
{
    /**
     * Creates a fork (AND-split) component that spawns one pointer per matching output branch.
     *
     * By default all branches fire unconditionally. Pass optional per-branch predicates via
     * $conditions to make specific branches conditional — branches without a predicate always fire.
     *
     * @param string[]                              $branches   Names of the parallel output branches.
     * @param array<string,null|TransitionCondition|callable(States):TransitionConditionDecision> $conditions Optional map of branch-name => predicate.
     */
    public static function create(string $uniqueName, array $branches, array $conditions = []): self
    {
        Assert::eachString($branches);

        $forkNode = new ForkNode('state-machines.fork.' . $uniqueName, [], new AllValidTransitionsStrategy());

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
            $instance->addTransition(
                $forkNode,
                $instance->output->{$branch}, // @phpstan-ignore-line
                $conditions[$branch] ?? null,
            );
        }

        return $instance;
    }
}

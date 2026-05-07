<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Parallel;

use PhpArchitecture\StateMachine\Foundation\Component\Parallel\Node\ParallelNode;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;

class ParallelComponent extends Definition
{
    /**
     * Creates a parallel (AND-split) component that spawns one pointer per matching output branch.
     *
     * By default all branches fire unconditionally. Pass optional per-branch predicates via
     * $conditions to make specific branches conditional — branches without a predicate always fire.
     *
     * Usage (unconditional):
     *   $parallel = ParallelComponent::create(['notifyEmail', 'notifySms', 'logAudit']);
     *
     * Usage (with conditions):
     *   $parallel = ParallelComponent::create(
     *       branches:   ['notifyEmail', 'notifySms', 'logAudit'],
     *       conditions: [
     *           'notifySms'  => fn(States $s): bool => $s->getState('user')?->details['hasSms']?->value === true,
     *           'logAudit'   => fn(States $s): bool => $s->getState('order')?->details['highValue']?->value === true,
     *       ],
     *   );
     *
     *   $machine->addDefinition($parallel);
     *
     *   $machine->addTransition($previousNode->id,            $parallel->input->trigger);
     *   $machine->addTransition($parallel->output->notifyEmail, $emailNode->id);
     *   $machine->addTransition($parallel->output->notifySms,   $smsNode->id);
     *   $machine->addTransition($parallel->output->logAudit,    $auditNode->id);
     *
     * @param string[]                               $branches   Names of the parallel output branches.
     * @param array<string, callable(States): bool>  $conditions Optional map of branch-name => predicate.
     */
    public static function create(array $branches, array $conditions = []): self
    {
        $instance = self::newInstance(
            inputs: ['trigger'],
            outputs: $branches,
        );

        $parallelNode = new ParallelNode();

        $instance->addTransition(
            $instance->input->trigger,
            $parallelNode,
            null,
        );

        foreach ($branches as $branch) {
            $predicate = $conditions[$branch] ?? null;

            $instance->addTransition(
                $parallelNode,
                $instance->output->{$branch},
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

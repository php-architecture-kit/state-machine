<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\SwitchCase;

use PhpArchitecture\StateMachine\Foundation\Component\SwitchCase\Node\SwitchCaseNode;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;

class SwitchCaseComponent extends Definition
{
    /**
     * Creates a conditional branch (XOR-gateway) component.
     *
     * Each branch is a named output whose transition fires when the corresponding predicate
     * returns true. Branches are evaluated in declaration order; the first matching branch
     * wins (backed by FirstValidTransitionStrategy on SwitchCaseNode).
     *
     * Usage:
     *   $branch = SwitchCaseComponent::create([
     *       'isApproved' => fn(States $s): bool => $s->getState('order')?->details['approved']?->value === true,
     *       'isRejected' => fn(States $s): bool => $s->getState('order')?->details['approved']?->value === false,
     *   ]);
     *
     *   $machine->addDefinition($branch);
     *
     *   Connect input and outputs to the rest of the graph:
     *   $machine->addTransition($previousNode->id,            $branch->input->trigger);
     *   $machine->addTransition($branch->output->isApproved, $approvedNode->id);
     *   $machine->addTransition($branch->output->isRejected, $rejectedNode->id);
     *
     * @param array<string, callable(States): bool> $branches  Map of output-name => predicate.
     */
    public static function create(array $branches): self
    {
        $outputNames = array_keys($branches);

        $instance = self::newInstance(
            inputs: ['trigger'],
            outputs: $outputNames,
        );

        $branchNode = new SwitchCaseNode();

        $instance->addTransition(
            $instance->input->trigger, // @phpstan-ignore-line
            $branchNode,
            null,
        );

        foreach ($branches as $outputName => $predicate) {
            $instance->addTransition(
                $branchNode,
                $instance->output->{$outputName},
                static function (States $states) use ($predicate): TransitionConditionDecision {
                    return $predicate($states)
                        ? TransitionConditionDecision::Accepted
                        : TransitionConditionDecision::Rejected;
                },
            );
        }

        return $instance;
    }
}

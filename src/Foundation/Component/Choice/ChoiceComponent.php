<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Choice;

use PhpArchitecture\StateMachine\Foundation\Component\Choice\Node\ChoiceNode;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;

class ChoiceComponent extends Definition
{
    /**
     * Creates a choice (XOR-gateway) component that routes to the first matching output branch.
     *
     * Branches are evaluated in declaration order; the first matching branch wins
     * (backed by FirstValidTransitionStrategy on ChoiceNode).
     *
     * @param array<string, callable(States): bool> $branches Map of output-name => predicate.
     */
    public static function create(array $branches): self
    {
        $choiceNode = new ChoiceNode();
        $outputNames = array_keys($branches);

        $instance = self::newInstance(
            $choiceNode->name(),
            inputs: ['trigger'],
            outputs: $outputNames,
        );

        $instance->addTransition(
            $instance->input->trigger, // @phpstan-ignore-line
            $choiceNode,
            null,
        );

        foreach ($branches as $outputName => $predicate) {
            $instance->addTransition(
                $choiceNode,
                $instance->output->{$outputName}, // @phpstan-ignore-line
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

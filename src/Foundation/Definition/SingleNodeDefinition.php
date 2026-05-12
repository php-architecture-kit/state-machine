<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Definition;

use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;

class SingleNodeDefinition extends Definition
{
    /**
     * @param array<string,null|TransitionCondition|callable(States):TransitionConditionDecision> $inputs
     * @param array<string,null|TransitionCondition|callable(States):TransitionConditionDecision> $outputs
     */
    public static function create(NodeInterface $node, array $inputs, array $outputs): static
    {
        $instance = static::newInstance(
            $node->name(),
            inputs: array_keys($inputs),
            outputs: array_keys($outputs),
        );

        $instance->addNode($node);

        foreach ($inputs as $input => $condition) {
            $instance->addTransition($instance->input->{$input}, $node, $condition, [$input]);
        }

        foreach ($outputs as $output => $condition) {
            $instance->addTransition($node, $instance->output->{$output}, $condition, [$output]);
        }

        return $instance;
    }
}

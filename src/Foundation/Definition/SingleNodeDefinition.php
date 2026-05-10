<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Definition;

use PhpArchitecture\StateMachine\Foundation\Node\NodeInterface;

class SingleNodeDefinition extends Definition
{
    /**
     * @param string[] $inputs
     * @param string[] $outputs
     */
    public static function create(NodeInterface $node, array $inputs, array $outputs): static
    {
        $instance = static::newInstance(
            $node->name(),
            inputs: $inputs,
            outputs: $outputs,
        );

        $instance->addNode($node);

        foreach ($inputs as $input) {
            $instance->addTransition($instance->input->{$input}, $node);
        }

        foreach ($outputs as $output) {
            $instance->addTransition($node, $instance->output->{$output});
        }

        return $instance;
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\RaceFirst\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;

/**
 * Handler that records the first pointer as winner in technical state.
 */
class RaceConditionNodeHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        $node = $context->node;
        assert($node instanceof RaceConditionNode);

        $state = $context->states->getTechnicalState();
        $stateName = $node->stateName();

        // First arrival sets the winner
        if ($state[$stateName] === null) {
            $context->states->modifyState($state->id, [$stateName => $context->pointer->id->toString()]);
        }

        return NodeHandlerResult::Continue;
    }
}

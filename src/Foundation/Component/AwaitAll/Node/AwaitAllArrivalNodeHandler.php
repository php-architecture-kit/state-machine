<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;

class AwaitAllArrivalNodeHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        $node = $context->node;
        assert($node instanceof AwaitAllArrivalNode);

        $state = $context->states->getTechnicalState();
        $stateName = $node->stateName();
        if ($state[$stateName] === null) {
            $context->states->modifyState($state->id, [$stateName => true]);
        }

        return NodeHandlerResult::Continue;
    }
}

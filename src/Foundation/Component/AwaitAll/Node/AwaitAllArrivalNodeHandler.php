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

        $stateName = 'join-arrived-' . $node->componentId;
        $state = $context->states->getState($stateName);

        if ($state === null) {
            $state = $context->states->defineState($stateName, []);
        }

        if ($state[$node->branchName] === null) {
            $context->states->modifyState($state->id, [$node->branchName => true]);
        }

        return NodeHandlerResult::Continue;
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\AwaitAll\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;

class AwaitAllArrivalNodeHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        /** @var AwaitAllArrivalNode $node */
        $node = $context->node;

        $stateKey = 'join-arrived-' . $node->componentId;
        $state = $context->states->getState($stateKey);

        if ($state === null) {
            $context->states->defineState($stateKey, [
                new StateDetail($node->branchName, true),
            ]);
        } else {
            $context->states->modifyState($state->id, [
                new StateDetail($node->branchName, true),
            ], []);
        }

        return NodeHandlerResult::Continue;
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Await\Node;

use DateInterval;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;

class AwaitStateNodeHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        $node = $context->node;
        assert($node instanceof AwaitStateNode);

        $state = $context->states->getTechnicalState();
        $context->states->modifyState($state->id, [
            $node->expiresAtStateName() => $node->clock->now()->add(new DateInterval('PT' . $node->timeout . 'S')),
        ]);

        return NodeHandlerResult::Continue;
    }
}

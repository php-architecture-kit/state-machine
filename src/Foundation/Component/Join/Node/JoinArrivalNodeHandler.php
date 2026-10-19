<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Join\Node;

use PhpArchitecture\StateMachine\Foundation\Component\Shared\TechnicalCounters;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;

/**
 * Counts the arrival. A handler runs exactly once per visit, however long the pointer then waits.
 */
class JoinArrivalNodeHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        $node = $context->node;
        assert($node instanceof JoinArrivalNode);

        TechnicalCounters::change($context->states, [$node->waitingCounter() => 1]);

        return NodeHandlerResult::Continue;
    }
}

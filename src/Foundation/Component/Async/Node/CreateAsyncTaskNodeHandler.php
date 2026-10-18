<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Async\Node;

use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerContext;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerInterface;
use PhpArchitecture\StateMachine\Foundation\Node\Handler\NodeHandlerResult;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Stamp\AwaitStateStamp;
use PhpArchitecture\StateMachine\Foundation\Task\Identity\TaskId;

class CreateAsyncTaskNodeHandler implements NodeHandlerInterface
{
    public function handle(NodeHandlerContext $context): NodeHandlerResult
    {
        $node = $context->node;
        assert($node instanceof CreateAsyncTaskNode);

        // a new visit: forget the result of a previous one and await only the task dispatched now
        $visitToken = TaskId::new()->toString();
        $technicalState = $context->states->getTechnicalState();
        $context->states->modifyState(
            $technicalState->id,
            [AwaitStateStamp::visitStateNameFor($node->stateName()) => $visitToken],
            [$node->stateName()],
        );

        $task = ($node->taskFactory)($context->states);
        $context->dispatchTask($task, [new AwaitStateStamp($node->stateName(), $visitToken)]);

        return NodeHandlerResult::Continue;
    }
}

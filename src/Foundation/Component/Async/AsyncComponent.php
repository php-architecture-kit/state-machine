<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Async;

use Closure;
use PhpArchitecture\StateMachine\Foundation\Component\Async\Node\AsyncNode;
use PhpArchitecture\StateMachine\Foundation\Component\Await\AwaitStateComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use Psr\Clock\ClockInterface;

class AsyncComponent extends Definition
{
    /**
     * Creates an async component that dispatches a Task and waits for its completion.
     *
     * The Task is dispatched exactly once when the pointer passes through AsyncNode.
     * An AwaitStateStamp carrying $stateName is added automatically to the dispatched Task,
     * so the external process that handles the task knows which state key to write back.
     *
     * The pointer then suspends (via AwaitStateComponent) until $stateName (optionally with
     * $detailName) appears in States. An optional $timeout drives the expired output.
     *
     * Outputs:
     *   - done    — the awaited state appeared before timeout (or no timeout set)
     *   - expired — the timeout elapsed before the state appeared (never fires when $timeout is null)
     *
     * @param Closure(States): Task $taskFactory
     */
    public static function create(
        string $stateName,
        Closure $taskFactory,
        ?string $detailName = null,
        ?int $timeout = null,
        ?ClockInterface $clock = null,
    ): self {
        $instance = self::newInstance(
            "state-machine.async.{$stateName}",
            inputs: ['trigger'],
            outputs: ['done', 'expired'],
        );

        $asyncNode = new AsyncNode("state-machine.async.{$stateName}.dispatch", $stateName, $taskFactory);
        $awaitComponent = AwaitStateComponent::create($stateName, $stateName, $detailName, $timeout, $clock);

        $awaitComponent->input->at->attach($asyncNode->id);
        $awaitComponent->output->run->attach($instance->output->done->id);
        $awaitComponent->output->expired->attach($instance->output->expired->id);

        [$awaitNodes, $awaitTransitions] = $awaitComponent->getDefinedNodesAndTransitions();

        $instance->addTransition($instance->input->trigger, $asyncNode, null);

        foreach ($awaitNodes as $node) {
            $instance->addNode($node);
        }

        foreach ($awaitTransitions as $transition) {
            $instance->addTransition($transition->input, $transition->output, $transition->condition);
        }

        return $instance;
    }
}

<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Async;

use PhpArchitecture\StateMachine\Foundation\Component\Async\Node\AsyncNode;
use PhpArchitecture\StateMachine\Foundation\Component\AwaitState\AwaitStateComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use Psr\Clock\ClockInterface;
use Closure;

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
     * Usage:
     *   $async = AsyncComponent::create(
     *       stateName:   'payment_result',
     *       taskFactory: fn(States $s): Task => new ProcessPaymentTask($s->getState('order')?->details['id']?->value),
     *       timeout:     60,
     *   );
     *
     *   $machine->addDefinition($async);
     *
     *   $machine->addTransition($previousNode->id,    $async->input->trigger);
     *   $machine->addTransition($async->output->done,    $nextNode->id);
     *   $machine->addTransition($async->output->expired, $expiredNode->id);
     *
     * @param string                        $stateName   Name of the state to wait for after task dispatch.
     * @param Closure(States $s): Task     $taskFactory Factory that builds the Task to dispatch.
     * @param string|null                   $detailName  Optional detail key that must also be present on the state.
     * @param int|null                      $timeout     Timeout in seconds. When null the expired output never fires.
     * @param ClockInterface|null           $clock       Clock for expiration. Defaults to UTC.
     */
    public static function create(
        string $stateName,
        Closure $taskFactory,
        ?string $detailName = null,
        ?int $timeout = null,
        ?ClockInterface $clock = null,
    ): self {
        $instance = self::newInstance(
            inputs: ['trigger'],
            outputs: ['done', 'expired'],
        );

        $asyncNode = new AsyncNode("php-architecture.async.{$stateName}", $stateName, $taskFactory);

        $awaitComponent = AwaitStateComponent::create($stateName, $detailName, $timeout, $clock);
        $awaitComponent->input->trigger->attach($asyncNode->id); // @phpstan-ignore-line
        $awaitComponent->output->done->attach($instance->output->done->id); // @phpstan-ignore-line
        $awaitComponent->output->expired->attach($instance->output->expired->id); // @phpstan-ignore-line

        [$awaitNodes, $awaitTransitions] = $awaitComponent->getDefinedNodesAndTransitions();

        $instance->addTransition(
            $instance->input->trigger, // @phpstan-ignore-line
            $asyncNode,
            null,
        );

        foreach ($awaitNodes as $node) {
            $instance->addNode($node);
        }

        foreach ($awaitTransitions as $transition) {
            $instance->addTransition($transition->from, $transition->to, $transition->condition); // @phpstan-ignore-line
        }

        return $instance;
    }
}

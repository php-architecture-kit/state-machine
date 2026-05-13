<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Async;

use Closure;
use PhpArchitecture\StateMachine\Foundation\Component\Async\Node\AsyncTaskExecutedNode;
use PhpArchitecture\StateMachine\Foundation\Component\Async\Node\AsyncTaskResult;
use PhpArchitecture\StateMachine\Foundation\Component\Async\Node\CreateAsyncTaskNode;
use PhpArchitecture\StateMachine\Foundation\Component\Await\AwaitStateComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PhpArchitecture\StateMachine\Foundation\Transition\Condition\Output\TransitionConditionDecision;
use PhpArchitecture\StateMachine\Foundation\Transition\Strategy\Default\FirstValidTransitionStrategy;
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
     * The pointer then suspends (via AwaitStateComponent) until $stateName appears in States.
     * An optional $timeout drives the expired output.
     *
     * Outputs:
     *   - success — the awaited state appeared with AsyncTaskResult::Success
     *   - fail    — the awaited state appeared with AsyncTaskResult::Fail
     *   - expired — the timeout elapsed before the state appeared
     *
     * @param Closure(States):Task $taskFactory
     */
    public static function create(
        string $uniqueName,
        Closure $taskFactory,
        ?int $timeout = null,
        ?ClockInterface $clock = null,
    ): self {
        $baseName = "state-machine.async-task.{$uniqueName}";

        $instance = self::newInstance(
            $baseName,
            inputs: ['trigger'],
            outputs: ['success', 'fail', 'expired'],
        );

        $createAsyncTaskNode = new CreateAsyncTaskNode("{$baseName}.dispatch", $taskFactory);
        $instance->addNode($createAsyncTaskNode);
        $instance->addTransition($instance->input->trigger->id(), $createAsyncTaskNode->id(), null);
        
        $executedTaskNode = new AsyncTaskExecutedNode("{$baseName}.executed", [], new FirstValidTransitionStrategy());
        $instance->addNode($executedTaskNode);

        $awaitComponent = AwaitStateComponent::create(
            $uniqueName,
            State::Technical,
            $createAsyncTaskNode->stateName(),
            $timeout,
            $clock,
        );

        $awaitComponent->input->at->attach($createAsyncTaskNode->id);
        $awaitComponent->output->run->attach($executedTaskNode->id);

        $instance->embed($awaitComponent, [], []);

        $instance->addTransition(
            $awaitComponent->output->expired->id(),
            $instance->output->expired->id(),
            null,
        );

        $instance->addTransition(
            $awaitComponent->output->run->id(),
            $instance->output->success->id(),
            static fn(States $states): TransitionConditionDecision => $states->getTechnicalState()[$createAsyncTaskNode->stateName()] === AsyncTaskResult::Success
                ? TransitionConditionDecision::Accepted
                : TransitionConditionDecision::Rejected,
        );

        $instance->addTransition(
            $awaitComponent->output->run->id(),
            $instance->output->fail->id(),
            static fn(States $states): TransitionConditionDecision => $states->getTechnicalState()[$createAsyncTaskNode->stateName()] === AsyncTaskResult::Fail
                ? TransitionConditionDecision::Accepted
                : TransitionConditionDecision::Rejected,
        );

        return $instance;
    }
}

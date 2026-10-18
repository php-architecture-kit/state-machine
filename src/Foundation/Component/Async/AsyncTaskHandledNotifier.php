<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Component\Async;

use PhpArchitecture\StateMachine\Foundation\Component\Async\Exception\MissingAwaitStateStampException;
use PhpArchitecture\StateMachine\Foundation\Component\Async\Node\AsyncTaskResult;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Stamp\AwaitStateStamp;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;

/**
 * Notifies the state machine that an async task has completed.
 *
 * Sets the result in Technical state's details, where the key is extracted
 * from AwaitStateStamp in the task envelope. Results of tasks dispatched by an
 * earlier visit of the same node are ignored.
 */
final readonly class AsyncTaskHandledNotifier
{
    public static function success(Execution $execution, TaskEnvelope $envelope): bool
    {
        return self::notify($execution, $envelope, AsyncTaskResult::Success);
    }

    public static function fail(Execution $execution, TaskEnvelope $envelope): bool
    {
        return self::notify($execution, $envelope, AsyncTaskResult::Fail);
    }

    /**
     * @return bool false when the result belongs to a task dispatched by an earlier visit
     *              (e.g. a late or duplicated delivery after a cycle) and was ignored
     */
    public static function notify(
        Execution $execution,
        TaskEnvelope $envelope,
        AsyncTaskResult $result,
    ): bool {
        $stamp = self::extractStamp($envelope);
        $technicalState = $execution->states->getTechnicalState();

        if ($stamp->visitToken !== null && $technicalState[$stamp->visitStateName()]?->value !== $stamp->visitToken) {
            return false;
        }

        $execution->states->modifyState(
            $technicalState->id,
            [new StateDetail($stamp->stateName, $result->value)],
            [],
        );

        return true;
    }

    private static function extractStamp(TaskEnvelope $envelope): AwaitStateStamp
    {
        $stamps = $envelope->getStamps(
            static fn($stamp): bool => $stamp instanceof AwaitStateStamp,
        );

        if (empty($stamps)) {
            throw MissingAwaitStateStampException::create();
        }

        /** @var AwaitStateStamp $stamp */
        $stamp = array_values($stamps)[0];

        return $stamp;
    }
}

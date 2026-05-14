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
 * from AwaitStateStamp in the task envelope.
 */
final readonly class AsyncTaskHandledNotifier
{
    public static function success(Execution $execution, TaskEnvelope $envelope): void
    {
        self::notify($execution, $envelope, AsyncTaskResult::Success);
    }

    public static function fail(Execution $execution, TaskEnvelope $envelope): void
    {
        self::notify($execution, $envelope, AsyncTaskResult::Fail);
    }

    public static function notify(
        Execution $execution,
        TaskEnvelope $envelope,
        AsyncTaskResult $result,
    ): void {
        $key = self::extractKey($envelope);
        $technicalState = $execution->states->getTechnicalState();

        $execution->states->modifyState(
            $technicalState->id,
            [new StateDetail($key, $result->value)],
            [],
        );
    }

    private static function extractKey(TaskEnvelope $envelope): string
    {
        $stamps = $envelope->getStamps(
            static fn($stamp): bool => $stamp instanceof AwaitStateStamp,
        );

        if (empty($stamps)) {
            throw MissingAwaitStateStampException::create();
        }

        /** @var AwaitStateStamp $stamp */
        $stamp = $stamps[0];

        return $stamp->stateName;
    }
}

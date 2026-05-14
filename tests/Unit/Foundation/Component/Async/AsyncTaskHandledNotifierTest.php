<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Component\Async;

use PhpArchitecture\StateMachine\Foundation\Component\Async\Exception\MissingAwaitStateStampException;
use PhpArchitecture\StateMachine\Foundation\Component\Async\Node\AsyncTaskResult;
use PhpArchitecture\StateMachine\Foundation\Component\Async\AsyncTaskHandledNotifier;
use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Stamp\AwaitStateStamp;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AsyncTaskHandledNotifierTest extends TestCase
{
    private function makeTask(): Task
    {
        return new class implements Task {
            public function execute(): void {}
        };
    }

    private function makeExecution(): Execution
    {
        return Execution::create();
    }

    private function makeEnvelopeWithStamp(string $stateName = 'test_task'): TaskEnvelope
    {
        return TaskEnvelope::create($this->makeTask(), [new AwaitStateStamp($stateName)]);
    }

    private function makeEnvelopeWithoutStamp(): TaskEnvelope
    {
        return TaskEnvelope::create($this->makeTask());
    }

    #[Test]
    public function successSetsTaskResultToSuccessInTechnicalState(): void
    {
        $execution = $this->makeExecution();
        $envelope = $this->makeEnvelopeWithStamp('my_async_task');

        AsyncTaskHandledNotifier::success($execution, $envelope);

        $technicalState = $execution->states->getTechnicalState();
        $this->assertSame(AsyncTaskResult::Success->value, $technicalState['my_async_task']?->value);
    }

    #[Test]
    public function failSetsTaskResultToFailInTechnicalState(): void
    {
        $execution = $this->makeExecution();
        $envelope = $this->makeEnvelopeWithStamp('failed_task');

        AsyncTaskHandledNotifier::fail($execution, $envelope);

        $technicalState = $execution->states->getTechnicalState();
        $this->assertSame(AsyncTaskResult::Fail->value, $technicalState['failed_task']?->value);
    }

    #[Test]
    public function notifyWithSuccessResultSetsCorrectValue(): void
    {
        $execution = $this->makeExecution();
        $envelope = $this->makeEnvelopeWithStamp('custom_task');

        AsyncTaskHandledNotifier::notify($execution, $envelope, AsyncTaskResult::Success);

        $technicalState = $execution->states->getTechnicalState();
        $this->assertSame(AsyncTaskResult::Success->value, $technicalState['custom_task']?->value);
    }

    #[Test]
    public function notifyWithFailResultSetsCorrectValue(): void
    {
        $execution = $this->makeExecution();
        $envelope = $this->makeEnvelopeWithStamp('custom_task');

        AsyncTaskHandledNotifier::notify($execution, $envelope, AsyncTaskResult::Fail);

        $technicalState = $execution->states->getTechnicalState();
        $this->assertSame(AsyncTaskResult::Fail->value, $technicalState['custom_task']?->value);
    }

    #[Test]
    public function throwsMissingAwaitStateStampExceptionWhenEnvelopeHasNoStamp(): void
    {
        $execution = $this->makeExecution();
        $envelope = $this->makeEnvelopeWithoutStamp();

        $this->expectException(MissingAwaitStateStampException::class);

        AsyncTaskHandledNotifier::success($execution, $envelope);
    }

    #[Test]
    public function multipleNotificationsCanSetDifferentKeysInTechnicalState(): void
    {
        $execution = $this->makeExecution();

        $envelope1 = $this->makeEnvelopeWithStamp('task_a');
        $envelope2 = $this->makeEnvelopeWithStamp('task_b');

        AsyncTaskHandledNotifier::success($execution, $envelope1);
        AsyncTaskHandledNotifier::fail($execution, $envelope2);

        $technicalState = $execution->states->getTechnicalState();
        $this->assertSame(AsyncTaskResult::Success->value, $technicalState['task_a']?->value);
        $this->assertSame(AsyncTaskResult::Fail->value, $technicalState['task_b']?->value);
    }

    #[Test]
    public function secondNotificationOverwritesFirstValueForSameKey(): void
    {
        $execution = $this->makeExecution();
        $envelope = $this->makeEnvelopeWithStamp('same_task');

        AsyncTaskHandledNotifier::success($execution, $envelope);
        AsyncTaskHandledNotifier::fail($execution, $envelope);

        $technicalState = $execution->states->getTechnicalState();
        $this->assertSame(AsyncTaskResult::Fail->value, $technicalState['same_task']?->value);
    }
}

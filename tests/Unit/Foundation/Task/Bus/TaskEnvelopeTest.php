<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Task\Bus;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Identity\PointerId;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Dispatch\DuplicateTaskKeyStampException;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Dispatch\InvalidTaskStampException;
use PhpArchitecture\StateMachine\Foundation\Task\Identity\TaskKey;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskEnvelope;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskStamp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

class TaskEnvelopeTest extends TestCase
{
    private function makeTask(): Task
    {
        return new class implements Task {};
    }

    private function makeStamp(): TaskStamp
    {
        return new class implements TaskStamp {};
    }

    private function makeKey(): TaskKey
    {
        return new TaskKey(NodeId::create("state-machine.unit.foundation.task.bus.taskenvelopetest.node1"), ExecutionId::new(), PointerId::new(), 'MyTask');
    }

    #[Test]
    public function createReturnsEnvelopeWithProvidedTask(): void
    {
        $task = $this->makeTask();

        $envelope = TaskEnvelope::create($task);

        $this->assertSame($task, $envelope->task);
    }

    #[Test]
    public function createGeneratesNonNullTaskId(): void
    {
        $envelope = TaskEnvelope::create($this->makeTask());

        $this->assertNotNull($envelope->id);
    }

    #[Test]
    public function createSetsKeyNullWhenNoTaskKeyStampProvided(): void
    {
        $envelope = TaskEnvelope::create($this->makeTask(), [$this->makeStamp()]);

        $this->assertNull($envelope->key);
    }

    #[Test]
    public function createExtractsKeyFromProvidedTaskKeyStamp(): void
    {
        $key = $this->makeKey();

        $envelope = TaskEnvelope::create($this->makeTask(), [$key]);

        $this->assertSame($key, $envelope->key);
    }

    #[Test]
    public function createThrowsInvalidTaskStampExceptionOnNonStampInArray(): void
    {
        $this->expectException(InvalidTaskStampException::class);

        TaskEnvelope::create($this->makeTask(), [new stdClass()]);
    }

    #[Test]
    public function createThrowsDuplicateTaskKeyStampExceptionWhenMultipleKeyStampsGiven(): void
    {
        $this->expectException(DuplicateTaskKeyStampException::class);

        TaskEnvelope::create($this->makeTask(), [$this->makeKey(), $this->makeKey()]);
    }

    #[Test]
    public function addStampAppendsStampToEnvelope(): void
    {
        $stamp = $this->makeStamp();
        $envelope = TaskEnvelope::create($this->makeTask());

        $envelope->addStamp($stamp);

        $this->assertContains($stamp, $envelope->getStamps());
    }

    #[Test]
    public function addStampSetsKeyWhenTaskKeyStampAdded(): void
    {
        $key = $this->makeKey();
        $envelope = TaskEnvelope::create($this->makeTask());

        $envelope->addStamp($key);

        $this->assertSame($key, $envelope->key);
    }

    #[Test]
    public function addStampThrowsDuplicateTaskKeyStampExceptionWhenKeyAlreadySet(): void
    {
        $envelope = TaskEnvelope::create($this->makeTask(), [$this->makeKey()]);

        $this->expectException(DuplicateTaskKeyStampException::class);

        $envelope->addStamp($this->makeKey());
    }

    #[Test]
    public function getStampsReturnsAllStampsWhenNoFilterGiven(): void
    {
        $stampA = $this->makeStamp();
        $stampB = $this->makeStamp();
        $envelope = TaskEnvelope::create($this->makeTask(), [$stampA, $stampB]);

        $stamps = $envelope->getStamps();

        $this->assertCount(2, $stamps);
        $this->assertContains($stampA, $stamps);
        $this->assertContains($stampB, $stamps);
    }

    #[Test]
    public function getStampsReturnsFilteredResultsWhenCallableGiven(): void
    {
        $key = $this->makeKey();
        $plainStamp = $this->makeStamp();
        $envelope = TaskEnvelope::create($this->makeTask(), [$key, $plainStamp]);

        $keyStamps = $envelope->getStamps(fn(TaskStamp $s) => $s instanceof TaskKey);

        $this->assertCount(1, $keyStamps);
        $this->assertContains($key, $keyStamps);
    }
}

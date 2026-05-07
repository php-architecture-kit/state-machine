<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Task\Bus;

use DateTimeImmutable;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Default\DeferredTaskBus;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\Stamp\DeferredAtStamp;
use PhpArchitecture\StateMachine\Foundation\Task\Task;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

class DeferredTaskBusTest extends TestCase
{
    private function makeTask(): Task
    {
        return new class implements Task {};
    }

    private function makeBus(DateTimeImmutable $now = new DateTimeImmutable()): DeferredTaskBus
    {
        $clock = new class($now) implements ClockInterface {
            public function __construct(private readonly DateTimeImmutable $now) {}

            public function now(): DateTimeImmutable
            {
                return $this->now;
            }
        };

        return new DeferredTaskBus($clock);
    }

    #[Test]
    public function dispatchReturnsEnvelopeWithGivenTask(): void
    {
        $task = $this->makeTask();
        $bus = $this->makeBus();

        $envelope = $bus->dispatch($task);

        $this->assertSame($task, $envelope->task);
    }

    #[Test]
    public function dispatchAddsEnvelopeToDeferredTasksArray(): void
    {
        $task = $this->makeTask();
        $bus = $this->makeBus();

        $envelope = $bus->dispatch($task);

        $this->assertCount(1, $bus->defferedTasks);
        $this->assertSame($envelope, $bus->defferedTasks[0]);
    }

    #[Test]
    public function multipleDispatchCallsAccumulateAllEnvelopes(): void
    {
        $bus = $this->makeBus();

        $bus->dispatch($this->makeTask());
        $bus->dispatch($this->makeTask());
        $bus->dispatch($this->makeTask());

        $this->assertCount(3, $bus->defferedTasks);
    }

    #[Test]
    public function dispatchAddsDeferredAtStampToEnvelope(): void
    {
        $bus = $this->makeBus();

        $envelope = $bus->dispatch($this->makeTask());

        $deferredAtStamps = $envelope->getStamps(fn($s) => $s instanceof DeferredAtStamp);
        $this->assertCount(1, $deferredAtStamps);
    }

    #[Test]
    public function deferredAtStampRecordsTimeFromClock(): void
    {
        $now = new DateTimeImmutable('2024-06-01 12:00:00');
        $bus = $this->makeBus($now);

        $envelope = $bus->dispatch($this->makeTask());

        $deferredAtStamps = $envelope->getStamps(fn($s) => $s instanceof DeferredAtStamp);
        /** @var DeferredAtStamp $stamp */
        $stamp = reset($deferredAtStamps);
        $this->assertEquals($now, $stamp->deferredAt);
    }
}

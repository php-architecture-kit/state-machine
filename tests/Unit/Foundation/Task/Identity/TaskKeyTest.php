<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Foundation\Task\Identity;

use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\Identity\PointerId;
use PhpArchitecture\StateMachine\Foundation\Task\Exception\Identity\InvalidTaskKeyFormatException;
use PhpArchitecture\StateMachine\Foundation\Task\Identity\TaskKey;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TaskKeyTest extends TestCase
{
    private function makeKey(string $taskName = 'MyTask'): TaskKey
    {
        return new TaskKey(
            NodeId::new(),
            ExecutionId::new(),
            PointerId::new(),
            $taskName,
        );
    }

    #[Test]
    public function constructorStoresAllProperties(): void
    {
        $nodeId = NodeId::new();
        $executionId = ExecutionId::new();
        $pointerId = PointerId::new();

        $key = new TaskKey($nodeId, $executionId, $pointerId, 'MyTask');

        $this->assertTrue($nodeId->equals($key->nodeId));
        $this->assertTrue($executionId->equals($key->executionId));
        $this->assertTrue($pointerId->equals($key->pointerId));
        $this->assertSame('MyTask', $key->taskName);
    }

    #[Test]
    public function toStringReturnsTaskPrefixedFormat(): void
    {
        $nodeId = NodeId::new();
        $executionId = ExecutionId::new();
        $pointerId = PointerId::new();

        $key = new TaskKey($nodeId, $executionId, $pointerId, 'MyTask');

        $expected = sprintf(
            'task:%s:%s:%s:MyTask',
            $nodeId->toString(),
            $executionId->toString(),
            $pointerId->toString(),
        );

        $this->assertSame($expected, (string) $key);
    }

    #[Test]
    public function fromStringReconstructsFromOwnToString(): void
    {
        $original = $this->makeKey('App\\Task\\SendEmailTask');

        $reconstructed = TaskKey::fromString((string) $original);

        $this->assertTrue($original->nodeId->equals($reconstructed->nodeId));
        $this->assertTrue($original->executionId->equals($reconstructed->executionId));
        $this->assertTrue($original->pointerId->equals($reconstructed->pointerId));
        $this->assertSame($original->taskName, $reconstructed->taskName);
    }

    #[Test]
    public function fromStringThrowsInvalidTaskKeyFormatExceptionWhenTooFewParts(): void
    {
        $this->expectException(InvalidTaskKeyFormatException::class);

        TaskKey::fromString('task:only-two-parts');
    }

    #[Test]
    public function fromStringThrowsInvalidTaskKeyFormatExceptionWhenPrefixIsNotTask(): void
    {
        $nodeId = NodeId::new();
        $executionId = ExecutionId::new();
        $pointerId = PointerId::new();

        $malformed = sprintf(
            'wrong:%s:%s:%s:MyTask',
            $nodeId->toString(),
            $executionId->toString(),
            $pointerId->toString(),
        );

        $this->expectException(InvalidTaskKeyFormatException::class);

        TaskKey::fromString($malformed);
    }
}

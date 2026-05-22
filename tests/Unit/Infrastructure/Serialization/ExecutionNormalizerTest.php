<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Tests\Unit\Infrastructure\Serialization;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\NodeHandlingStatus;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\StateMachine\Infrastructure\Serialization\ExecutionNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExecutionNormalizerTest extends TestCase
{
    private ExecutionNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ExecutionNormalizer();
    }

    #[Test]
    public function normalizeAndDenormalizePreservesExecutionId(): void
    {
        $execution = Execution::create();

        $data = $this->normalizer->normalize($execution);
        $restored = $this->normalizer->denormalize($data);

        $this->assertTrue($execution->id->equals($restored->id));
    }

    #[Test]
    public function normalizeAndDenormalizePreservesPointers(): void
    {
        $nodeId = NodeId::create("state-machine.unit.infra.persistence.normalizer.node1");
        $execution = Execution::create();
        $execution->pointers->startAt($nodeId);

        $data = $this->normalizer->normalize($execution);
        $restored = $this->normalizer->denormalize($data);

        $this->assertCount(1, $restored->pointers->pointers);
        $pointer = array_values($restored->pointers->pointers)[0];
        $this->assertTrue($nodeId->equals($pointer->nodeId));
        $this->assertSame(NodeHandlingStatus::Pending, $pointer->handlingStatus);
    }

    #[Test]
    public function normalizeAndDenormalizePreservesPointerParentIds(): void
    {
        $nodeId = NodeId::create("state-machine.unit.infra.persistence.normalizer.node2");
        $execution = Execution::create();
        $original = $execution->pointers->startAt($nodeId);
        $forked = $execution->pointers->fork($original->id);

        $data = $this->normalizer->normalize($execution);
        $restored = $this->normalizer->denormalize($data);

        $pointers = array_values($restored->pointers->pointers);
        $restoredForked = array_filter($pointers, static fn($p) => $p->id->equals($forked->id));
        $restoredForked = array_values($restoredForked)[0];

        $this->assertCount(1, $restoredForked->parentIds);
        $this->assertTrue($original->id->equals($restoredForked->parentIds[0]));
    }

    #[Test]
    public function normalizeScalarStateDetail(): void
    {
        $nodeId = NodeId::create("state-machine.unit.infra.persistence.normalizer.node3");
        $execution = Execution::create();
        $execution->pointers->startAt($nodeId);
        $execution->states->defineState('payment', [new StateDetail('amount', 42)]);

        $data = $this->normalizer->normalize($execution);

        $stateData = array_values(array_filter($data['states'], static fn($s) => $s['name'] === 'payment'))[0];
        $detail = $stateData['details']['amount'];
        $this->assertSame('raw', $detail['type']);
        $this->assertSame(42, $detail['value']);
    }

    #[Test]
    public function normalizeNullStateDetail(): void
    {
        $nodeId = NodeId::create("state-machine.unit.infra.persistence.normalizer.node4");
        $execution = Execution::create();
        $execution->pointers->startAt($nodeId);
        $execution->states->defineState('order', [new StateDetail('note', null)]);

        $data = $this->normalizer->normalize($execution);

        $stateData = array_values(array_filter($data['states'], static fn($s) => $s['name'] === 'order'))[0];
        $this->assertSame('raw', $stateData['details']['note']['type']);
        $this->assertNull($stateData['details']['note']['value']);
    }

    #[Test]
    public function normalizeStringStateDetail(): void
    {
        $nodeId = NodeId::create("state-machine.unit.infra.persistence.normalizer.node5");
        $execution = Execution::create();
        $execution->pointers->startAt($nodeId);
        $execution->states->defineState('user', [new StateDetail('name', 'Alice')]);

        $data = $this->normalizer->normalize($execution);
        $restored = $this->normalizer->denormalize($data);

        $state = $restored->states->getState('user');
        $this->assertNotNull($state);
        $this->assertSame('Alice', $state['name']?->value);
    }

    #[Test]
    public function normalizeObjectStateDetailUsesSerialize(): void
    {
        $nodeId = NodeId::create("state-machine.unit.infra.persistence.normalizer.node6");
        $execution = Execution::create();
        $execution->pointers->startAt($nodeId);
        $obj = new NormalizerTestValueObject('test');
        $execution->states->defineState('config', [new StateDetail('obj', $obj)]);

        $data = $this->normalizer->normalize($execution);

        $stateData = array_values(array_filter($data['states'], static fn($s) => $s['name'] === 'config'))[0];
        $this->assertSame('serialized', $stateData['details']['obj']['type']);
    }

    #[Test]
    public function denormalizeObjectStateDetailRestoresObject(): void
    {
        $nodeId = NodeId::create("state-machine.unit.infra.persistence.normalizer.node7");
        $execution = Execution::create();
        $execution->pointers->startAt($nodeId);
        $obj = new NormalizerTestValueObject('restored');
        $execution->states->defineState('config', [new StateDetail('obj', $obj)]);

        $data = $this->normalizer->normalize($execution);
        $restored = $this->normalizer->denormalize($data);

        $state = $restored->states->getState('config');
        $this->assertNotNull($state);
        $restoredObj = $state['obj']?->value;
        $this->assertInstanceOf(NormalizerTestValueObject::class, $restoredObj);
        $this->assertSame('restored', $restoredObj->value);
    }

    #[Test]
    public function normalizeNonSerializableValueThrowsRuntimeException(): void
    {
        $nodeId = NodeId::create("state-machine.unit.infra.persistence.normalizer.node8");
        $execution = Execution::create();
        $execution->pointers->startAt($nodeId);
        $closure = static fn() => 'hello';
        $execution->states->defineState('bad', [new StateDetail('fn', $closure)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot normalize StateDetail 'fn'");

        $this->normalizer->normalize($execution);
    }

    #[Test]
    public function normalizeArrayStateDetail(): void
    {
        $nodeId = NodeId::create("state-machine.unit.infra.persistence.normalizer.node9");
        $execution = Execution::create();
        $execution->pointers->startAt($nodeId);
        $execution->states->defineState('data', [new StateDetail('items', [1, 2, 3])]);

        $data = $this->normalizer->normalize($execution);
        $restored = $this->normalizer->denormalize($data);

        $state = $restored->states->getState('data');
        $this->assertNotNull($state);
        $this->assertSame([1, 2, 3], $state['items']?->value);
    }

    #[Test]
    public function normalizeAndDenormalizePreservesMultipleStates(): void
    {
        $nodeId = NodeId::create("state-machine.unit.infra.persistence.normalizer.node10");
        $execution = Execution::create();
        $execution->pointers->startAt($nodeId);
        $execution->states->defineState('payment', [new StateDetail('amount', 100)]);
        $execution->states->defineState('shipping', [new StateDetail('address', 'Paris')]);

        $data = $this->normalizer->normalize($execution);
        $restored = $this->normalizer->denormalize($data);

        $this->assertNotNull($restored->states->getState('payment'));
        $this->assertNotNull($restored->states->getState('shipping'));
    }

    #[Test]
    public function normalizedArrayContainsExpectedKeys(): void
    {
        $execution = Execution::create();

        $data = $this->normalizer->normalize($execution);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('pointers', $data);
        $this->assertArrayHasKey('states', $data);
        $this->assertSame($execution->id->toString(), $data['id']);
    }
}

class NormalizerTestValueObject
{
    public function __construct(public readonly string $value) {}
}

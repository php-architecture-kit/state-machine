<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Serialization;

use PhpArchitecture\StateMachine\Foundation\Execution\Execution;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\Node\Identity\NodeId;
use PhpArchitecture\StateMachine\Foundation\Pointer\NodeHandlingStatus;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointer;
use PhpArchitecture\StateMachine\Foundation\Pointer\Pointers;
use PhpArchitecture\StateMachine\Foundation\Pointer\Identity\PointerId;
use PhpArchitecture\StateMachine\Foundation\State\State;
use PhpArchitecture\StateMachine\Foundation\State\States;
use PhpArchitecture\StateMachine\Foundation\State\Identity\StateId;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use RuntimeException;

class ExecutionNormalizer implements ExecutionNormalizerInterface
{
    public function normalize(Execution $execution): array
    {
        return [
            'id' => $execution->id->toString(),
            'pointers' => array_values(array_map(
                fn(Pointer $pointer): array => $this->normalizePointer($pointer),
                $execution->pointers->pointers,
            )),
            'states' => array_values(array_map(
                fn(State $state): array => $this->normalizeState($state),
                $execution->states->states,
            )),
        ];
    }

    public function denormalize(array $data): Execution
    {
        $executionId = ExecutionId::fromString($data['id']);

        $pointers = array_map(
            fn(array $p): Pointer => $this->denormalizePointer($p),
            $data['pointers'],
        );

        $states = array_map(
            fn(array $s): State => $this->denormalizeState($s),
            $data['states'],
        );

        return Execution::recreate(
            $executionId,
            Pointers::recreate($executionId, null, null, null, $pointers),
            States::recreate($executionId, null, null, null, [], $states),
        );
    }

    private function normalizePointer(Pointer $pointer): array
    {
        return [
            'id' => $pointer->id->toString(),
            'executionId' => $pointer->executionId->toString(),
            'parentIds' => array_map(
                static fn(PointerId $id): string => $id->toString(),
                $pointer->parentIds,
            ),
            'nodeId' => $pointer->nodeId->toString(),
            'currentStep' => $pointer->currentStep,
            'handlingStatus' => $pointer->handlingStatus->value,
        ];
    }

    private function denormalizePointer(array $data): Pointer
    {
        return Pointer::recreate(
            ExecutionId::fromString($data['executionId']),
            PointerId::fromString($data['id']),
            array_map(static fn(string $id): PointerId => PointerId::fromString($id), $data['parentIds']),
            NodeId::fromString($data['nodeId']),
            $data['currentStep'],
            NodeHandlingStatus::from($data['handlingStatus']),
        );
    }

    private function normalizeState(State $state): array
    {
        return [
            'id' => $state->id->toString(),
            'executionId' => $state->executionId->toString(),
            'name' => $state->name,
            'details' => array_map(
                fn(StateDetail $detail): array => $this->normalizeDetail($detail),
                $state->details,
            ),
        ];
    }

    private function denormalizeState(array $data): State
    {
        $details = array_map(
            fn(array $d): StateDetail => $this->denormalizeDetail($d),
            $data['details'],
        );

        return State::recreate(
            ExecutionId::fromString($data['executionId']),
            StateId::fromString($data['id']),
            $data['name'],
            array_values($details),
        );
    }

    private function normalizeDetail(StateDetail $detail): array
    {
        $value = $detail->value;

        if (is_scalar($value) || $value === null) {
            return ['name' => $detail->name, 'type' => 'raw', 'value' => $value];
        }

        if (is_array($value)) {
            try {
                json_encode($value, JSON_THROW_ON_ERROR);
                return ['name' => $detail->name, 'type' => 'raw', 'value' => $value];
            } catch (\JsonException) {
                // fall through to serialize
            }
        }

        try {
            $serialized = serialize($value);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                sprintf("Cannot normalize StateDetail '%s': value of type '%s' is not serializable.", $detail->name, get_debug_type($value)),
                previous: $e,
            );
        }

        return ['name' => $detail->name, 'type' => 'serialized', 'value' => base64_encode($serialized)];
    }

    private function denormalizeDetail(array $data): StateDetail
    {
        $value = $data['type'] === 'serialized'
            ? unserialize(base64_decode($data['value']))
            : $data['value'];

        return new StateDetail($data['name'], $value);
    }
}

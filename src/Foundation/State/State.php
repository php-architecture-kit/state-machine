<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State;

use ArrayAccess;
use LogicException;
use PhpArchitecture\StateMachine\Foundation\Execution\Identity\ExecutionId;
use PhpArchitecture\StateMachine\Foundation\State\Exception\Definition\InvalidInputProvidedToStateDefinition;
use PhpArchitecture\StateMachine\Foundation\State\Identity\StateId;
use PhpArchitecture\StateMachine\Foundation\State\Property\StateDetail;
use PhpArchitecture\Technical\ArrayTransformation;
use PhpArchitecture\Technical\Assert;

/**
 * @implements ArrayAccess<string,StateDetail>
 */
class State implements ArrayAccess
{
    public const Technical = '__technical';

    /**
     * @var array<string,StateDetail>
     */
    public readonly array $details;

    /** 
     * @param StateDetail[] $details
     */
    protected function __construct(
        public readonly ExecutionId $executionId,
        public readonly StateId $id,
        public readonly string $name,
        array $details,
    ) {
        Assert::eachInstanceOf($details, StateDetail::class);
        $this->details = ArrayTransformation::indexBy($details, static fn(StateDetail $detail) => $detail->name);
    }

    /**
     * Accepts plain values keyed by detail name and StateDetail objects; a plain value becomes a StateDetail.
     *
     * @param array<int,StateDetail>|array<string,mixed> $details
     * @throws InvalidInputProvidedToStateDefinition when a value without a detail name is not a StateDetail
     */
    public static function create(
        ExecutionId $executionId,
        string $name,
        array $details,
    ): static {
        $mappedDetails = StateDetail::mapAll($name, $details, InvalidInputProvidedToStateDefinition::class);

        /** @phpstan-ignore-next-line */
        return new static(
            $executionId,
            StateId::new(),
            $name,
            $mappedDetails,
        );
    }

    /**
     * Restores a persisted state, whose details are StateDetail objects.
     *
     * @param StateDetail[] $details
     */
    public static function recreate(
        ExecutionId $executionId,
        StateId $id,
        string $name,
        array $details,
    ): static {
        $mappedDetails = [];
        foreach ($details as $key => $value) {
            if ($value instanceof StateDetail) {
                $mappedDetails[] = $value;
            } else {
                $mappedDetails[] = new StateDetail($key, $value);
            }
        }

        /** @phpstan-ignore-next-line */
        return new static(
            $executionId,
            $id,
            $name,
            $mappedDetails,
        );
    }

    /** The value of the detail, or `$default` when the detail does not exist (a stored `null` is returned as `null`). */
    public function value(string $detail, mixed $default = null): mixed
    {
        return isset($this->details[$detail]) ? $this->details[$detail]->value : $default;
    }

    /** @return array<string,mixed> detail name => value */
    public function values(): array
    {
        return array_map(static fn(StateDetail $detail): mixed => $detail->value, $this->details);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->details[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->details[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('State is a immutable class. Use States class: `$states->modifyState($state->id, ...)` instead.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('State is a immutable class. Use States class: `$states->modifyState($state->id, ...)` instead.');
    }
}

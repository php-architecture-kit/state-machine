<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\State\Property;

use Throwable;

final readonly class StateDetail
{
    public function __construct(
        public readonly string $name,
        public readonly mixed $value,
    ) {}

    /**
     * Maps the details given to State and States: a plain value is keyed by its detail name, a value under
     * a numeric key has no name and must already be a StateDetail. What a detail holds is up to the author.
     *
     * @internal
     * @param array<int,StateDetail>|array<string,mixed> $details
     * @param class-string<Throwable> $exceptionClass thrown for a value without a detail name
     * @return array<string,StateDetail> detail name => detail; a later detail with the same name wins
     */
    public static function mapAll(string $stateName, array $details, string $exceptionClass): array
    {
        $mapped = [];
        foreach ($details as $key => $value) {
            if (!$value instanceof self) {
                if (!is_string($key)) {
                    throw new $exceptionClass(sprintf(
                        'State "%s": the detail at index %d has no name, so it must be a %s, %s given.',
                        $stateName,
                        $key,
                        self::class,
                        get_debug_type($value),
                    ));
                }
                $value = new self($key, $value);
            }
            $mapped[$value->name] = $value;
        }

        return $mapped;
    }

    public function withValue(mixed $value): self
    {
        return new self($this->name, $value);
    }
}

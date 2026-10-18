<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Bus\Stamp;

use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskStamp;

final readonly class AwaitStateStamp implements TaskStamp
{
    /**
     * @param ?string $visitToken identifies the visit that dispatched the task; a result carrying
     *                            a token of another visit is stale and must not be written back
     */
    public function __construct(
        public string $stateName,
        public ?string $visitToken = null,
    ) {}

    /** Name of the technical state detail holding the token of the visit currently awaiting the result. */
    public function visitStateName(): string
    {
        return self::visitStateNameFor($this->stateName);
    }

    public static function visitStateNameFor(string $stateName): string
    {
        return $stateName . '.visit';
    }
}

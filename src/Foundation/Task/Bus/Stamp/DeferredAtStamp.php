<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Foundation\Task\Bus\Stamp;

use DateTimeImmutable;
use PhpArchitecture\StateMachine\Foundation\Task\Bus\TaskStamp;
use Psr\Clock\ClockInterface;

final readonly class DeferredAtStamp implements TaskStamp
{
    public function __construct(
        public DateTimeImmutable $deferredAt,
    ) {}

    public static function create(ClockInterface $clock): self
    {
        return new self($clock->now());
    }
}

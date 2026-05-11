<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Presentation\View;

class EdgeView
{
    /**
     * @param array<string, mixed> $__otherProperties
     */
    public function __construct(
        public readonly string $id,
        public readonly string $class,
        public readonly string $from,
        public readonly string $to,
        public readonly array $__otherProperties = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge(
            [
                'id'    => $this->id,
                'class' => $this->class,
                'from'  => $this->from,
                'to'    => $this->to,
            ],
            $this->__otherProperties,
        );
    }
}

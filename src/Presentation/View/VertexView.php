<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Presentation\View;

class VertexView
{
    /**
     * @param array<string, mixed> $__otherProperties
     */
    public function __construct(
        public readonly string $id,
        public readonly string $class,
        public readonly array $__otherProperties = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge(
            [
                'id'    => $this->id,
                'class' => $this->class,
            ],
            $this->__otherProperties,
        );
    }
}

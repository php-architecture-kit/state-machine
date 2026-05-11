<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Presentation\View;

class NodeView
{
    /**
     * @param string[]             $tags
     * @param array<string, mixed> $__otherProperties
     */
    public function __construct(
        public readonly string $id,
        public readonly string $class,
        public readonly string $globallyUniqueName,
        public readonly string $handlerClass,
        public readonly string $transitionSelectionStrategy,
        public readonly array $tags,
        public readonly array $__otherProperties = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge(
            [
                'id'                         => $this->id,
                'class'                      => $this->class,
                'globallyUniqueName'         => $this->globallyUniqueName,
                'handlerClass'               => $this->handlerClass,
                'transitionSelectionStrategy' => $this->transitionSelectionStrategy,
                'tags'                       => $this->tags,
            ],
            $this->__otherProperties,
        );
    }
}

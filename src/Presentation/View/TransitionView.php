<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Presentation\View;

class TransitionView
{
    /**
     * @param string[]             $tags
     * @param array<string, mixed> $__otherProperties
     */
    public function __construct(
        public readonly string $id,
        public readonly string $from,
        public readonly string $to,
        public readonly array $tags,
        public readonly ?TransitionConditionView $condition = null,
        public readonly array $__otherProperties = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge(
            [
                'id'        => $this->id,
                'from'      => $this->from,
                'to'        => $this->to,
                'tags'      => $this->tags,
                'condition' => $this->condition?->toArray(),
            ],
            $this->__otherProperties,
        );
    }
}

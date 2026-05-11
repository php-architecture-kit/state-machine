<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy;

use PhpArchitecture\Graph\Edge\EdgeInterface;
use PhpArchitecture\StateMachine\Foundation\Transition\TransitionInterface;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Exception\UnsupportedResourceException;
use PhpArchitecture\StateMachine\Presentation\View\EdgeView;

class EdgeMappingStrategy implements StateMachineViewMappingStrategy
{
    public function supports(object $resource): bool
    {
        return $resource instanceof EdgeInterface && !$resource instanceof TransitionInterface;
    }

    public function mapToView(object $resource): EdgeView
    {
        if (!$resource instanceof EdgeInterface) {
            throw new UnsupportedResourceException(
                sprintf('EdgeMappingStrategy only supports %s, got %s.', EdgeInterface::class, get_class($resource)),
            );
        }

        return new EdgeView(
            id: $resource->id()->toString(),
            class: get_class($resource),
            from: $resource->u()->toString(),
            to: $resource->v()->toString(),
        );
    }
}

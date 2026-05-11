<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy;

interface StateMachineViewMappingStrategy
{
    public function supports(object $resource): bool;

    public function mapToView(object $resource): object;
}

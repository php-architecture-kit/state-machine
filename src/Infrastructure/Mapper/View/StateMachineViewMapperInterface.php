<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View;

use PhpArchitecture\StateMachine\Presentation\View\StateMachineView;

interface StateMachineViewMapperInterface
{
    public function map(object $resource): StateMachineView;
}

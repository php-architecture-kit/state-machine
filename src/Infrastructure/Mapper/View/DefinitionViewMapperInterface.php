<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Presentation\View\DefinitionView;

interface DefinitionViewMapperInterface
{
    public function map(Definition $definition): DefinitionView;
}

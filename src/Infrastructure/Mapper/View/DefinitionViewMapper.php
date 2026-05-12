<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View;

use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Exception\UnsupportedResourceException;
use PhpArchitecture\StateMachine\Presentation\View\DefinitionView;

class DefinitionViewMapper implements DefinitionViewMapperInterface
{
    /** @var StateMachineViewMappingStrategy[] */
    private array $strategies;

    /**
     * @param StateMachineViewMappingStrategy[] $strategies
     */
    public function __construct(array $strategies)
    {
        $this->strategies = $strategies;
    }

    public function map(Definition $definition): DefinitionView
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($definition)) {
                $result = $strategy->mapToView($definition);
                if ($result instanceof DefinitionView) {
                    return $result;
                }
                throw new UnsupportedResourceException(
                    sprintf('Strategy %s returned %s instead of DefinitionView.', get_class($strategy), get_class($result)),
                );
            }
        }

        throw new UnsupportedResourceException(
            sprintf('No strategy found to map Definition of type %s.', get_class($definition)),
        );
    }
}

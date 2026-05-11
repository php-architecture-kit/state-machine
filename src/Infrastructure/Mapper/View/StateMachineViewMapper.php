<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Mapper\View;

use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Exception\UnsupportedResourceException;
use PhpArchitecture\StateMachine\Infrastructure\Mapper\View\Strategy\StateMachineViewMappingStrategy;
use PhpArchitecture\StateMachine\Presentation\View\StateMachineView;

class StateMachineViewMapper implements StateMachineViewMapperInterface
{
    /**
     * @param StateMachineViewMappingStrategy[] $strategies
     */
    public function __construct(
        private readonly array $strategies,
    ) {}

    public function map(object $resource): StateMachineView
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($resource)) {
                $view = $strategy->mapToView($resource);

                if (!$view instanceof StateMachineView) {
                    throw new UnsupportedResourceException(
                        sprintf(
                            'Strategy %s::mapToView() must return %s, got %s.',
                            get_class($strategy),
                            StateMachineView::class,
                            get_class($view),
                        ),
                    );
                }

                return $view;
            }
        }

        throw new UnsupportedResourceException(
            sprintf('No mapping strategy supports resource of type %s.', get_class($resource)),
        );
    }
}

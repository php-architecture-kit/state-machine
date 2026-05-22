<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Transition\Condition\TransitionCondition;
use Psr\Container\ContainerInterface;

abstract class AbstractComponentBuilder implements ComponentBuilderInterface
{
    protected function resolveCondition(?string $conditionClass, ContainerInterface $container): ?TransitionCondition
    {
        if ($conditionClass === null) {
            return null;
        }

        if ($container->has($conditionClass)) {
            /** @var TransitionCondition $condition */
            $condition = $container->get($conditionClass);
            return $condition;
        }

        return new $conditionClass();
    }
}

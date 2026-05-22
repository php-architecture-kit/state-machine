<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Component\Choice\ChoiceComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use Psr\Container\ContainerInterface;

final class ChoiceComponentBuilder extends AbstractComponentBuilder
{
    public function supports(string $type): bool
    {
        return $type === 'choice';
    }

    public function build(array $config, ContainerInterface $container): Definition
    {
        $branches = [];
        foreach ($config['branches'] as $branchName => $conditionClass) {
            $branches[$branchName] = $this->resolveCondition($conditionClass, $container);
        }

        return ChoiceComponent::create($config['name'], $branches);
    }
}

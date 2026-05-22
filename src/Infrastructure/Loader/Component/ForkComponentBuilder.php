<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Component\Fork\ForkComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use Psr\Container\ContainerInterface;

final class ForkComponentBuilder extends AbstractComponentBuilder
{
    public function supports(string $type): bool
    {
        return $type === 'fork';
    }

    public function build(array $config, ContainerInterface $container): Definition
    {
        $branches = [];
        $conditions = [];

        foreach ($config['branches'] as $branch) {
            $branches[] = $branch['port'];
            if (isset($branch['condition'])) {
                $conditions[$branch['port']] = $this->resolveCondition($branch['condition'], $container);
            }
        }

        return ForkComponent::create($config['name'], $branches, $conditions);
    }
}

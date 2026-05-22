<?php

declare(strict_types=1);

namespace PhpArchitecture\StateMachine\Infrastructure\Loader\Component;

use PhpArchitecture\StateMachine\Foundation\Component\RaceFirst\RaceFirstComponent;
use PhpArchitecture\StateMachine\Foundation\Definition\Definition;
use Psr\Container\ContainerInterface;

final class RaceFirstComponentBuilder extends AbstractComponentBuilder
{
    public function supports(string $type): bool
    {
        return $type === 'race_first';
    }

    public function build(array $config, ContainerInterface $container): Definition
    {
        return RaceFirstComponent::create($config['name']);
    }
}
